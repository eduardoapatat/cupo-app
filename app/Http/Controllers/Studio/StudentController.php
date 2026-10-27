<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Students\EnrollStudent;
use App\Actions\Students\SendActivation;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StudentRequest;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Alumnas»: the studio's students, for the owner and the front desk. A
 * student enrolled at the counter has no app until she activates her
 * account; until then the front desk keeps her contact data. Once she uses
 * the app, her data is hers, so nobody in the studio edits it.
 */
class StudentController extends Controller
{
    #[Authorize('students.manage', 'studio')]
    public function index(Studio $studio): Response
    {
        $students = $studio->memberships()
            ->where('memberships.role', StudioRole::Student)
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->orderByRaw('lower(users.name)')
            ->orderBy('memberships.id')
            ->select('memberships.*')
            ->with('user')
            ->paginate(25)
            ->through(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'phone' => $membership->user->phone,
                'email' => $membership->user->email,
                'status' => match (true) {
                    $membership->user->usesApp() => 'app',
                    $membership->user->email !== null => 'activation_sent',
                    default => 'no_app',
                },
            ]);

        return Inertia::render('studios/students', [
            'students' => $students,
            'enrollmentUrl' => route('enrollment.show', $studio->enrollment_token),
        ]);
    }

    #[Authorize('students.manage', 'studio')]
    public function store(StudentRequest $request, Studio $studio, EnrollStudent $enrollStudent): RedirectResponse
    {
        $membership = $enrollStudent->handle($studio, $request->studentName(), $request->studentPhone(), $request->studentEmail());

        Inertia::flash('toast', ['type' => 'success', 'message' => $membership->user->email === null
            ? __(':name is now a student of the studio.', ['name' => $membership->user->name])
            : __(':name is now a student of the studio. We sent her the link to activate her account.', ['name' => $membership->user->name]),
        ]);

        return to_route('studios.students.index', $studio);
    }

    /**
     * Correct the contact data of a student without the app. Adding or
     * changing her email sends her the activation link there.
     */
    #[Authorize('students.manage', 'studio')]
    public function update(StudentRequest $request, Studio $studio, Membership $membership, SendActivation $sendActivation): RedirectResponse
    {
        abort_unless($membership->role === StudioRole::Student, 404);
        abort_if($membership->user->usesApp(), 403);

        $student = $membership->user;
        $student->fill([
            'name' => $request->studentName(),
            'phone' => $request->studentPhone(),
            'email' => $request->studentEmail(),
        ]);
        $emailChanged = $student->isDirty('email');
        $student->save();

        $sent = $emailChanged && $sendActivation->handle($student, $studio);

        Inertia::flash('toast', ['type' => 'success', 'message' => $sent
            ? __('We saved the data and sent the activation link to :email.', ['email' => $student->email])
            : __('We saved the data.'),
        ]);

        return to_route('studios.students.index', $studio);
    }
}
