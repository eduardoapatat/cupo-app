<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Students\EnrollStudent;
use App\Actions\Students\SendActivation;
use App\Enums\StudentStatus;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StudentRequest;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    /**
     * The students, searched by name, email or phone and filtered by
     * whether they use the app. The counts per tab follow the search.
     */
    #[Authorize('students.manage', 'studio')]
    public function index(Request $request, Studio $studio): Response
    {
        $search = mb_substr(trim($request->string('buscar')->toString()), 0, 100);
        $status = StudentStatus::fromQuery($request->string('estado')->toString());

        $query = $studio->memberships()
            ->where('memberships.role', StudioRole::Student)
            ->join('users', 'users.id', '=', 'memberships.user_id');

        if ($search !== '') {
            $this->whereMatches($query, $search);
        }

        $countsSql = 'count(*) as total';

        foreach (StudentStatus::cases() as $case) {
            $countsSql .= ", count(*) filter (where {$case->condition()}) as {$case->value}";
        }

        $counts = (array) (clone $query)->toBase()->selectRaw($countsSql)->first();
        $tabCounts = ['all' => (int) $counts['total']];

        foreach (StudentStatus::cases() as $case) {
            $tabCounts[$case->value] = (int) $counts[$case->value];
        }

        if ($status !== null) {
            $query->whereRaw($status->condition());
        }

        $timezone = config()->string('studio.timezone');

        $students = $query
            ->orderByRaw('lower(users.name)')
            ->orderBy('memberships.id')
            ->select('memberships.*')
            ->with('user')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'phone' => $membership->user->phone,
                'email' => $membership->user->email,
                'status' => StudentStatus::for($membership->user)->value,
                'enrolledOn' => $membership->created_at?->timezone($timezone)->translatedFormat('j \d\e F \d\e Y'),
            ]);

        return Inertia::render('studios/students', [
            'students' => $students,
            'filters' => ['search' => $search, 'status' => $status?->queryValue()],
            'counts' => $tabCounts,
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

        return back(fallback: route('studios.students.index', $studio));
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

        return back(fallback: route('studios.students.index', $studio));
    }

    /**
     * Match the search in the name (ignoring case and accents) or the email,
     * and its digits in the phone. LIKE wildcards are searched literally.
     *
     * @param  HasMany<Membership, Studio>  $query
     */
    private function whereMatches(HasMany $query, string $search): void
    {
        $pattern = '%'.addcslashes($search, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $search) ?? '';

        $query->where(function (Builder $query) use ($pattern, $digits): void {
            $query->whereRaw('unaccent(users.name) ilike unaccent(?)', [$pattern])
                ->orWhere('users.email', 'ilike', $pattern);

            if (strlen($digits) >= 3) {
                $query->orWhere('users.phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
