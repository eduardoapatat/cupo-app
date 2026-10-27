<?php

namespace App\Http\Controllers;

use App\Actions\Team\AcceptInvitation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The link an invited person opens from their email. It is public: the
 * person may not have an account yet. The link is signed and expires with
 * the invitation, so its id cannot be guessed or reused after it expires.
 */
class InvitationController extends Controller
{
    /**
     * Show the invitation, or why it no longer works. What the page offers
     * depends on who opens it: a form to create the account, a login, or a
     * button to accept.
     */
    public function show(Request $request, int $invitation): SymfonyResponse
    {
        $found = $this->find($request, $invitation);

        if (! $found instanceof Invitation) {
            return $found;
        }

        $user = $request->user();
        $hasAccount = $user === null && User::where('email', $found->email)->exists();

        if ($hasAccount) {
            // Fortify takes them back here after they sign in.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('invitations/show', [
            'invitation' => [
                'studio' => $found->studio->name,
                'role' => $found->role->label(),
                'invitedBy' => $found->invitedBy?->name,
                'email' => $found->email,
                'expiresOn' => $found->expires_at->timezone(config()->string('studio.timezone'))->translatedFormat('j \d\e F'),
            ],
            'state' => match (true) {
                $user === null => $hasAccount ? 'login' : 'register',
                mb_strtolower((string) $user->email) !== $found->email => 'other_account',
                $user->roleIn($found->studio) !== null => 'member',
                default => 'accept',
            },
            'studioUrl' => route('studios.home', $found->studio),
            'acceptUrl' => URL::temporarySignedRoute('invitations.accept', $found->expires_at, ['invitation' => $found->id]),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ])->toResponse($request);
    }

    /**
     * Accept the invitation, creating the account first for a guest, and
     * take the new member to the studio.
     */
    public function accept(AcceptInvitationRequest $request, int $invitation, AcceptInvitation $acceptInvitation): SymfonyResponse
    {
        $found = $this->find($request, $invitation);

        if (! $found instanceof Invitation) {
            return $found;
        }

        $user = $request->user() ?? $this->register($request, $found);

        if (mb_strtolower((string) $user->email) !== $found->email) {
            abort(403);
        }

        if ($user->roleIn($found->studio) === null) {
            $acceptInvitation->handle($found, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You joined :studio.', ['studio' => $found->studio->name])]);
        }

        return to_route('studios.home', $found->studio);
    }

    /**
     * The invitation the signed link points to, or the page that explains
     * why it no longer works. A wrong signature and a missing invitation
     * get the same 404, so nobody can probe which invitations exist.
     */
    private function find(Request $request, int $id): Invitation|SymfonyResponse
    {
        $invitation = Invitation::with('studio', 'invitedBy')->find($id);

        [$reason, $status] = match (true) {
            $invitation === null, ! URL::hasCorrectSignature($request) => ['invalid', 404],
            $invitation->isAccepted() => ['accepted', 410],
            $invitation->isExpired(), ! URL::signatureHasNotExpired($request) => ['expired', 410],
            default => [null, 200],
        };

        if ($reason === null) {
            return $invitation;
        }

        return Inertia::render('invitations/unavailable', ['reason' => $reason])
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * Create the guest's account with the invited email, already verified
     * because the link arrived there, and sign them in. Someone who already
     * has an account must sign in instead.
     */
    private function register(AcceptInvitationRequest $request, Invitation $invitation): User
    {
        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('You already have an account. Sign in to accept the invitation.'),
            ]);
        }

        $user = User::create([
            'name' => $request->string('name')->trim()->value(),
            'email' => $invitation->email,
            'password' => $request->string('password')->value(),
            'adult_declared_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));
        Auth::login($user);

        return $user;
    }
}
