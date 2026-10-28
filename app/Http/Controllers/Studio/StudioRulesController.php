<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Classes\GenerateSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StudioRulesRequest;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class StudioRulesController extends Controller
{
    /**
     * Save the studio's rules. A longer booking horizon generates the new
     * days' sessions at once, instead of waiting for the nightly run.
     */
    #[Authorize('rules.manage', 'studio')]
    public function update(StudioRulesRequest $request, Studio $studio, GenerateSessions $generateSessions): RedirectResponse
    {
        $studio->update($request->studioRules());

        $generateSessions->handle($studio);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We saved the rules. They apply from now on.')]);

        return to_route('studios.settings.edit', $studio);
    }
}
