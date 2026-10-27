<?php

namespace App\Http\Controllers\Studio;

use App\Enums\Commune;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudioRequest;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's own data, edited by its owner from the «Estudio» item.
 */
class SettingsController extends Controller
{
    /**
     * Show the studio's data.
     */
    #[Authorize('studio.manage', 'studio')]
    public function edit(Studio $studio): Response
    {
        return Inertia::render('studios/settings', [
            'details' => [
                'slug' => $studio->slug,
                'name' => $studio->name,
                'commune' => $studio->commune->value,
            ],
            'communes' => Commune::options(),
        ]);
    }

    /**
     * Save the studio's name and commune. The slug stays, so links keep
     * working.
     */
    #[Authorize('studio.manage', 'studio')]
    public function update(StudioRequest $request, Studio $studio): RedirectResponse
    {
        $studio->update([
            'name' => $request->studioName(),
            'commune' => $request->commune(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Studio saved.')]);

        return to_route('studios.settings.edit', $studio);
    }
}
