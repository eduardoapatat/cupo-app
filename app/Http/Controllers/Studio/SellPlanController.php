<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\SellPlanRequest;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Vender plan»: the front desk or the owner sells a plan at the counter,
 * at its list price. Only the owner changes the price or gives the plan
 * away (plans.discount), always saying why.
 */
class SellPlanController extends Controller
{
    #[Authorize('plans.sell', 'studio')]
    public function create(Request $request, Studio $studio, #[CurrentUser] User $user): Response
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $student = $request->filled('alumna')
            ? $this->studentMembership($studio, $request->integer('alumna'))?->user
            : null;

        return Inertia::render('studios/sell-plan', [
            'plans' => $studio->plans()->active()->orderBy('price')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'credits' => $plan->credits,
                'validityDays' => $plan->validity_days,
                'dailyLimit' => $plan->daily_limit,
                'lastDay' => $today->addDays($plan->validity_days - 1)->translatedFormat('l j \d\e F'),
            ]),
            'student' => $student === null ? null : $this->presentStudent($studio, $student),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'canChangePrice' => $user->can('plans.discount', $studio),
        ]);
    }

    /**
     * The students that match a search, to sell one of them a plan.
     */
    #[Authorize('plans.sell', 'studio')]
    public function students(Request $request, Studio $studio): JsonResponse
    {
        $search = mb_substr(trim($request->string('buscar')->toString()), 0, 100);

        $students = $studio->memberships()
            ->where('memberships.role', StudioRole::Student)
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->matchingSearch($search)))
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->orderByRaw('lower(users.name)')
            ->select('memberships.*')
            ->with('user')
            ->limit(10)
            ->get();

        return response()->json([
            'students' => $students->map(fn (Membership $membership): array => $this->presentStudent($studio, $membership->user)),
        ]);
    }

    #[Authorize('plans.sell', 'studio')]
    public function store(SellPlanRequest $request, Studio $studio, #[CurrentUser] User $user, SellPlan $sellPlan): RedirectResponse
    {
        $membership = $this->studentMembership($studio, $request->integer('user_id'));
        abort_if($membership === null, 404);
        $plan = $studio->plans()->active()->findOrFail($request->integer('plan_id'));
        $price = $request->price();
        abort_if($price !== null && $price !== $plan->price && ! $user->can('plans.discount', $studio), 403);

        $purchase = $sellPlan->handle($plan, $membership->user, $user, $request->paymentMethod(), $price, $request->priceReason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sold :plan to :name. It lasts until :day.', [
            'plan' => $purchase->name,
            'name' => $membership->user->name,
            'day' => $purchase->lastDay()->translatedFormat('l j \d\e F'),
        ])]);

        return to_route('studios.students.show', [$studio, $membership, 'pestana' => 'plan']);
    }

    private function studentMembership(Studio $studio, int $userId): ?Membership
    {
        return $studio->memberships()
            ->where('user_id', $userId)
            ->where('role', StudioRole::Student)
            ->with('user')
            ->first();
    }

    /**
     * A student as the sale shows her: who she is and the plans she has.
     *
     * @return array<string, mixed>
     */
    private function presentStudent(Studio $studio, User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'phone' => $student->phone,
            'plans' => $student->planPurchases()
                ->where('studio_id', $studio->id)
                ->valid()
                ->orderBy('expires_at')
                ->get()
                ->map(fn (PlanPurchase $purchase): array => [
                    'name' => $purchase->name,
                    'creditsLeft' => $purchase->credits_left,
                    'lastDay' => $purchase->lastDay()->translatedFormat('j \d\e F'),
                ]),
        ];
    }
}
