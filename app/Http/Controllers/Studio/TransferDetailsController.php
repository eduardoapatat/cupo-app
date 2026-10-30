<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\TransferDetailsRequest;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * Where students transfer to, from «Estudio». With them, students can buy
 * a plan from the app.
 */
class TransferDetailsController extends Controller
{
    #[Authorize('studio.manage', 'studio')]
    public function update(TransferDetailsRequest $request, Studio $studio): RedirectResponse
    {
        $studio->update(['transfer_details' => $request->transferDetails()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $studio->transfer_details === null
            ? __('Saved. Students cannot buy from the app until you add them.')
            : __('Saved. Students see them when they buy a plan from the app.'),
        ]);

        return to_route('studios.settings.edit', $studio);
    }
}
