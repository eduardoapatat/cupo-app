<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsPlanSummary;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi plan»: the student's plans in the studio, how many classes each has
 * left and until when, and every class taken or returned. Only hers: the
 * query starts from the current user.
 */
class MyPlanController extends Controller
{
    use PresentsPlanSummary;

    #[Authorize('self.view', 'studio')]
    public function index(Studio $studio, #[CurrentUser] User $user): Response
    {
        $purchases = $user->planPurchases()
            ->where('studio_id', $studio->id)
            ->withExpiredCredits()
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->get();
        $timezone = config()->string('studio.timezone');

        $movements = CreditMovement::query()
            ->whereIn('plan_purchase_id', $purchases->modelKeys())
            ->with('booking.classSession.classType', 'planPurchase')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        // Her latest purchase from the app: a transfer waiting to be reviewed
        // or rejected, or a Webpay payment still pending or not completed.
        $fromApp = $studio->payments()
            ->where('user_id', $user->id)
            ->whereNull('cash_register_id')
            ->whereNotNull('plan_id')
            ->with('plan')
            ->latest('id')
            ->first();

        return Inertia::render('studios/my-plan', [
            'summary' => $this->planSummary($user, $studio),
            'pendingPayment' => $fromApp?->status === PaymentStatus::ToReview ? [
                'planName' => $fromApp->plan?->name,
                'amount' => $fromApp->amount,
                'sentOn' => $fromApp->created_at?->timezone($timezone)->translatedFormat('j \d\e F'),
                'receiptUrl' => route('studios.payments.receipt', [$studio, $fromApp], absolute: false),
            ] : null,
            'rejectedPayment' => $fromApp?->status === PaymentStatus::Rejected ? [
                'planName' => $fromApp->plan?->name,
                'reason' => $fromApp->status_reason,
            ] : null,
            'webpayPending' => $fromApp?->status === PaymentStatus::Pending ? [
                'planName' => $fromApp->plan?->name,
                'amount' => $fromApp->amount,
            ] : null,
            'failedPayment' => $fromApp?->status === PaymentStatus::Failed ? [
                'planName' => $fromApp->plan?->name,
                'reason' => $fromApp->status_reason,
            ] : null,
            // Webpay is always on, so she can buy unless a receipt waits.
            'canBuy' => $fromApp?->status !== PaymentStatus::ToReview,
            'valid' => $purchases->filter(fn (PlanPurchase $purchase): bool => $purchase->isValid())
                ->map(fn (PlanPurchase $purchase): array => $this->present($purchase))
                ->values(),
            'past' => $purchases->reject(fn (PlanPurchase $purchase): bool => $purchase->isValid())
                ->map(fn (PlanPurchase $purchase): array => $this->present($purchase))
                ->values(),
            'movements' => $movements->map(fn (CreditMovement $movement): array => [
                'id' => $movement->id,
                'date' => $movement->created_at->timezone($timezone)->translatedFormat('j \d\e F'),
                'reason' => $movement->reason->label(),
                'delta' => $movement->delta,
                'planName' => $movement->planPurchase->name,
                'className' => $movement->booking?->classSession->classType->name,
                'classDate' => $movement->booking?->classSession->localStartsAt()->translatedFormat('l j \d\e F, H:i'),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PlanPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'name' => $purchase->name,
            'credits' => $purchase->credits,
            'creditsLeft' => $purchase->credits_left,
            'dailyLimit' => $purchase->daily_limit,
            'boughtOn' => $purchase->starts_on->translatedFormat('j \d\e F'),
            'lastDay' => $purchase->lastDay()->translatedFormat('l j \d\e F'),
            'isCancelled' => $purchase->isCancelled(),
            'expiredCredits' => $purchase->expiredCredits(),
        ];
    }
}
