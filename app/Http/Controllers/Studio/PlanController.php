<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\PlanRequest;
use App\Models\Plan;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Planes»: the owner's plans, as a list with the chosen one beside it.
 * Editing a plan never changes what was already sold.
 */
class PlanController extends Controller
{
    #[Authorize('plans.manage', 'studio')]
    public function index(Studio $studio): Response
    {
        return Inertia::render('studios/plans', [
            'plans' => $this->plans($studio),
            'plan' => null,
            'defaultDailyLimit' => config()->integer('studio.defaults.unlimited_plan_daily_limit'),
        ]);
    }

    #[Authorize('plans.manage', 'studio')]
    public function show(Studio $studio, Plan $plan): Response
    {
        return Inertia::render('studios/plans', [
            'plans' => $this->plans($studio),
            'plan' => $this->present($plan),
            'defaultDailyLimit' => config()->integer('studio.defaults.unlimited_plan_daily_limit'),
        ]);
    }

    #[Authorize('plans.manage', 'studio')]
    public function store(PlanRequest $request, Studio $studio): RedirectResponse
    {
        $plan = $studio->plans()->create($request->plan());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We added the plan :plan.', ['plan' => $plan->name])]);

        return to_route('studios.plans.show', [$studio, $plan]);
    }

    #[Authorize('plans.manage', 'studio')]
    public function update(PlanRequest $request, Studio $studio, Plan $plan): RedirectResponse
    {
        $plan->update($request->plan());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We saved the plan :plan. Plans already sold stay as they were.', ['plan' => $plan->name])]);

        return to_route('studios.plans.show', [$studio, $plan]);
    }

    /**
     * The plans, the ones on sale first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plans(Studio $studio): array
    {
        return $studio->plans()
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('price')
            ->orderByRaw('lower(name)')
            ->get()
            ->map(fn (Plan $plan): array => $this->present($plan))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => $plan->price,
            'credits' => $plan->credits,
            'validityDays' => $plan->validity_days,
            'dailyLimit' => $plan->daily_limit,
            'isArchived' => $plan->isArchived(),
        ];
    }
}
