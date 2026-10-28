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
     * whether they use the app.
     */
    #[Authorize('students.manage', 'studio')]
    public function index(Request $request, Studio $studio): Response
    {
        return Inertia::render('studios/students', [
            ...$this->listProps($request, $studio),
            'student' => null,
        ]);
    }

    /**
     * A student's profile, next to the list so the front desk can move
     * from one to another. Her bookings and plan join it later.
     */
    #[Authorize('students.manage', 'studio')]
    public function show(Request $request, Studio $studio, Membership $membership): Response
    {
        abort_unless($membership->role === StudioRole::Student, 404);

        return Inertia::render('studios/students', [
            ...$this->listProps($request, $studio),
            'student' => $this->present($membership),
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

        return to_route('studios.students.show', [$studio, $membership]);
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
     * The list of students, loaded as an infinite scroll, with the counts
     * per status that follow the search.
     *
     * @return array<string, mixed>
     */
    private function listProps(Request $request, Studio $studio): array
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
        $statusCounts = ['all' => (int) $counts['total']];

        foreach (StudentStatus::cases() as $case) {
            $statusCounts[$case->value] = (int) $counts[$case->value];
        }

        if ($status !== null) {
            $query->whereRaw($status->condition());
        }

        $students = $query
            ->orderByRaw('lower(users.name)')
            ->orderBy('memberships.id')
            ->select('memberships.*')
            ->with('user')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Membership $membership): array => $this->present($membership));

        return [
            'students' => Inertia::scroll($students),
            'filters' => ['search' => $search, 'status' => $status?->queryValue()],
            'counts' => $statusCounts,
            'enrollmentUrl' => route('enrollment.show', $studio->enrollment_token),
        ];
    }

    /**
     * @return array{id: int, name: string, phone: string|null, email: string|null, status: string, enrolledOn: string|null}
     */
    private function present(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'name' => $membership->user->name,
            'phone' => $membership->user->phone,
            'email' => $membership->user->email,
            'status' => StudentStatus::for($membership->user)->value,
            'enrolledOn' => $membership->created_at?->timezone(config()->string('studio.timezone'))->translatedFormat('j \d\e F \d\e Y'),
        ];
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
