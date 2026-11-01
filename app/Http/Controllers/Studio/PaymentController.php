<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Payments\VoidPayment;
use App\Concerns\PresentsPayments;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Pagos»: every payment of the studio, by status, and each one with its
 * trail. The front desk and the owner review them here, and void one taken
 * by mistake.
 */
class PaymentController extends Controller
{
    use PresentsPayments;

    /**
     * The tabs, by the Spanish word in the URL.
     */
    private const array STATUSES = [
        'por-validar' => PaymentStatus::ToReview,
        'aprobados' => PaymentStatus::Approved,
        'rechazados' => PaymentStatus::Rejected,
        'anulados' => PaymentStatus::Voided,
    ];

    #[Authorize('payments.review', 'studio')]
    public function index(Request $request, Studio $studio): Response
    {
        $toReview = $studio->payments()->where('status', PaymentStatus::ToReview)->count();
        // With receipts waiting, those come first.
        $status = $request->string('estado', $toReview > 0 ? 'por-validar' : 'todos')->toString();
        $statusFilter = self::STATUSES[$status] ?? null;
        $status = $statusFilter === null ? 'todos' : $status;
        $search = mb_substr(trim($request->string('buscar')->toString()), 0, 100);
        $timezone = config()->string('studio.timezone');
        $approved = fn (): Builder => $studio->payments()->getQuery()->where('status', PaymentStatus::Approved);

        $payments = $studio->payments()
            ->when($statusFilter, fn (Builder $query, PaymentStatus $filter) => $query->where('status', $filter))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->matchingSearch($search)))
            ->with(['user', 'plan', 'planPurchase', 'cashRegister'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('studios/payments', [
            'payments' => $payments->through(fn (Payment $payment): array => $this->presentPayment($payment)),
            'filters' => ['status' => $status, 'search' => $search],
            'toReview' => $toReview,
            'stats' => [
                'today' => (int) $approved()->where('paid_at', '>=', now($timezone)->startOfDay()->utc())->sum('amount'),
                'month' => (int) $approved()->where('paid_at', '>=', now($timezone)->startOfMonth()->utc())->sum('amount'),
                'voidedThisMonth' => $studio->payments()
                    ->where('status', PaymentStatus::Voided)
                    ->where('voided_at', '>=', now($timezone)->startOfMonth()->utc())
                    ->count(),
            ],
        ]);
    }

    #[Authorize('payments.review', 'studio')]
    public function show(Studio $studio, Payment $payment, #[CurrentUser] User $user, VoidPayment $voidPayment): Response
    {
        $payment->load(['user', 'plan', 'planPurchase', 'cashRegister.user', 'receivedBy', 'reviewedBy', 'voidedBy']);
        $purchase = $payment->planPurchase;
        $timezone = config()->string('studio.timezone');
        $membership = $studio->memberships()->where('user_id', $payment->user_id)->first();
        $voiding = Gate::forUser($user)->inspect('void', $payment);

        return Inertia::render('studios/payment', [
            'payment' => [
                ...$this->presentPayment($payment),
                'takenAt' => $payment->created_at?->timezone($timezone)->translatedFormat('l j \d\e F, H:i'),
                'receivedBy' => $payment->receivedBy?->name,
                'receipt' => $payment->receipt_path === null ? null : [
                    'url' => route('studios.payments.receipt', [$studio, $payment], absolute: false),
                    'isPdf' => str_ends_with($payment->receipt_path, '.pdf'),
                ],
                'reviewed' => $payment->reviewed_at === null ? null : [
                    'by' => $payment->reviewedBy?->name,
                    'at' => $payment->reviewed_at->timezone($timezone)->translatedFormat('l j \d\e F, H:i'),
                ],
                'studentProfileId' => $membership?->id,
                'register' => $payment->cashRegister === null ? null : [
                    'personName' => $payment->cashRegister->user->name ?? __('Former team member'),
                    'day' => $payment->cashRegister->business_date->translatedFormat('l j \d\e F'),
                    'isClosed' => $payment->cashRegister->isClosed(),
                ],
                'rejectionReason' => $payment->status === PaymentStatus::Rejected ? $payment->status_reason : null,
                'webpay' => $payment->method !== PaymentMethod::Webpay ? null : [
                    'buyOrder' => $payment->webpay_buy_order,
                    'authorizationCode' => $payment->webpay_authorization_code,
                    'cardLastDigits' => $payment->card_last_digits,
                    'failureReason' => $payment->status === PaymentStatus::Failed ? $payment->status_reason : null,
                ],
                'voided' => $payment->voided_at === null ? null : [
                    'by' => $payment->voidedBy?->name,
                    'at' => $payment->voided_at->timezone($timezone)->translatedFormat('l j \d\e F, H:i'),
                    'reason' => $payment->status_reason,
                ],
                'plan' => $purchase === null ? null : [
                    'name' => $purchase->name,
                    'listPrice' => $purchase->list_price,
                    'priceReason' => $purchase->price_reason,
                    'isUnlimited' => $purchase->isUnlimited(),
                    'credits' => $purchase->credits,
                    'creditsLeft' => $purchase->credits_left,
                    'lastDay' => $purchase->lastDay()->translatedFormat('j \d\e F'),
                    'isCancelled' => $purchase->isCancelled(),
                    'upcomingBookings' => $purchase->isCancelled() ? 0 : $voidPayment->upcomingBookings($purchase)->count(),
                ],
            ],
            'canReview' => $payment->status === PaymentStatus::ToReview,
            'canVoid' => $voiding->allowed(),
            // Why she cannot void it, when she could void others.
            'voidDenied' => $voiding->denied() && $user->can('payments.void_open', $studio) && $payment->status === PaymentStatus::Approved
                ? $voiding->message()
                : null,
        ]);
    }
}
