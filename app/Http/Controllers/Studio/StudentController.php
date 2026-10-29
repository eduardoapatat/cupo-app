<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Students\EnrollStudent;
use App\Actions\Students\SendActivation;
use App\Enums\StudentStatus;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StudentRequest;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PlanPurchase;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Builder;
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
            'canSellPlan' => $request->user()?->can('plans.sell', $studio) ?? false,
        ]);
    }

    /**
     * A student's profile, next to the list so the front desk can move
     * from one to another, with her plans and what she paid.
     */
    #[Authorize('students.manage', 'studio')]
    public function show(Request $request, Studio $studio, Membership $membership): Response
    {
        abort_unless($membership->role === StudioRole::Student, 404);

        return Inertia::render('studios/students', [
            ...$this->listProps($request, $studio),
            'student' => [
                ...$this->present($membership),
                'userId' => $membership->user_id,
                'purchases' => $this->purchases($studio, $membership),
            ],
            'canSellPlan' => $request->user()?->can('plans.sell', $studio) ?? false,
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
            $query->whereHas('user', fn (Builder $user) => $user->matchingSearch($search));
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
            'canRenewEnrollmentLink' => $request->user()?->can('studio.manage', $studio) ?? false,
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
     * Her plans in this studio, the valid ones first, each with its payment.
     *
     * @return array<int, array<string, mixed>>
     */
    private function purchases(Studio $studio, Membership $membership): array
    {
        return $membership->user->planPurchases()
            ->where('studio_id', $studio->id)
            ->with('payments')
            ->orderByRaw('expires_at <= now()')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PlanPurchase $purchase): array => [
                'id' => $purchase->id,
                'name' => $purchase->name,
                'credits' => $purchase->credits,
                'creditsLeft' => $purchase->credits_left,
                'isValid' => $purchase->expires_at->isFuture(),
                'boughtOn' => $purchase->created_at?->timezone(config()->string('studio.timezone'))->translatedFormat('j \d\e F'),
                'lastDay' => $purchase->lastDay()->translatedFormat('j \d\e F'),
                'payments' => $purchase->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                ])->all(),
            ])
            ->all();
    }
}
