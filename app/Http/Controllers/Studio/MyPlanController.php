<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsPlanSummary;
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

        return Inertia::render('studios/my-plan', [
            'summary' => $this->planSummary($user, $studio),
            'valid' => $purchases->filter(fn (PlanPurchase $purchase): bool => $purchase->expires_at->isFuture())
                ->map(fn (PlanPurchase $purchase): array => $this->present($purchase))
                ->values(),
            'past' => $purchases->reject(fn (PlanPurchase $purchase): bool => $purchase->expires_at->isFuture())
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
        ];
    }
}
