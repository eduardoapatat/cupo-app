<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Students\SendActivation;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class StudentActivationController extends Controller
{
    /**
     * Send again the activation link to a student without the app who has
     * an email. Nothing to send for anyone else.
     */
    #[Authorize('students.manage', 'studio')]
    public function store(Studio $studio, Membership $membership, SendActivation $sendActivation): RedirectResponse
    {
        abort_unless($membership->role === StudioRole::Student, 404);
        abort_unless($sendActivation->handle($membership->user, $studio), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent the activation link to :email again.', ['email' => $membership->user->email])]);

        return to_route('studios.students.index', $studio);
    }
}
