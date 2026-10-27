<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Where a student enrolled at the counter creates her password, from the
 * signed link in her email. Once she has one, the link stops working.
 */
class AccountActivationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, int $user): SymfonyResponse
    {
        $found = $this->find($request, $user);

        if (! $found instanceof User) {
            return $found;
        }

        return Inertia::render('activation/show', [
            'name' => $found->name,
            'email' => $found->email,
            // The form expires with the link from the email.
            'activateUrl' => URL::temporarySignedRoute(
                'activation.store',
                Date::createFromTimestamp($request->integer('expires')),
                ['user' => $found->id, 'hash' => $request->string('hash')->value()],
            ),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ])->toResponse($request);
    }

    /**
     * Save the password, mark the email verified (the link arrived there)
     * and sign her in.
     */
    public function store(Request $request, int $user): SymfonyResponse
    {
        $found = $this->find($request, $user);

        if (! $found instanceof User) {
            return $found;
        }

        $request->validate(['password' => $this->passwordRules()]);

        $found->forceFill([
            'password' => $request->string('password')->value(),
            'email_verified_at' => now(),
        ])->save();

        Auth::login($found);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your account is ready.')]);

        return to_route('dashboard');
    }

    /**
     * The user the signed link points to, or the page that explains why it
     * no longer works. A wrong signature, a missing user or an email that
     * changed since the link was sent get the same 404.
     */
    private function find(Request $request, int $id): User|SymfonyResponse
    {
        $user = User::find($id);

        [$reason, $status] = match (true) {
            $user === null,
            $user->email === null,
            ! URL::hasCorrectSignature($request),
            ! hash_equals(sha1($user->email), $request->string('hash')->value()) => ['invalid', 404],
            $user->usesApp() => ['active', 410],
            ! URL::signatureHasNotExpired($request) => ['expired', 410],
            default => [null, 200],
        };

        if ($reason === null) {
            return $user;
        }

        return Inertia::render('activation/unavailable', ['reason' => $reason])
            ->toResponse($request)
            ->setStatusCode($status);
    }
}
