<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A plan stops being sold (archived) or goes back on sale. Whoever bought
 * it keeps using it either way.
 */
class PlanArchiveController extends Controller
{
    #[Authorize('plans.manage', 'studio')]
    public function store(Studio $studio, Plan $plan): RedirectResponse
    {
        $plan->forceFill(['archived_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We archived :plan. It is no longer sold, and whoever has it keeps using it.', ['plan' => $plan->name])]);

        return to_route('studios.plans.show', [$studio, $plan]);
    }

    /**
     * Its name may have been taken meanwhile by a plan on sale.
     */
    #[Authorize('plans.manage', 'studio')]
    public function destroy(Studio $studio, Plan $plan): RedirectResponse
    {
        $taken = $studio->plans()->active()->whereRaw('lower(name) = ?', [mb_strtolower($plan->name)])->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('The studio already sells a plan with that name.')]);
        }

        $plan->forceFill(['archived_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':plan is on sale again.', ['plan' => $plan->name])]);

        return to_route('studios.plans.show', [$studio, $plan]);
    }
}
