<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class EnrollmentLinkController extends Controller
{
    /**
     * Give the studio a new enrollment link. The old one stops working at
     * once, for example after it was shared where it should not have been.
     */
    #[Authorize('studio.manage', 'studio')]
    public function update(Studio $studio): RedirectResponse
    {
        $studio->forceFill(['enrollment_token' => Studio::newEnrollmentToken()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The studio has a new enrollment link. The old one no longer works.')]);

        return to_route('studios.settings.edit', $studio);
    }
}
