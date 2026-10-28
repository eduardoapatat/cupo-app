<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\CancelSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\CancelSessionRequest;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class SessionCancellationController extends Controller
{
    /**
     * Cancel a class with its reason. Its bookings are cancelled on time.
     */
    #[Authorize('sessions.cancel', 'studio')]
    public function store(CancelSessionRequest $request, Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, CancelSession $cancelSession): RedirectResponse
    {
        try {
            $cancelSession->handle($classSession, $user, $request->reason());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We cancelled the class. Its bookings were cancelled on time.')]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }
}
