<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Studios\JoinStudioAsStudent;
use App\Concerns\ProfileValidationRules;
use App\Models\Studio;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The studio's public enrollment link, which the owner shares (Instagram,
 * WhatsApp). Whoever creates an account through it, or joins with the one
 * they have, becomes a student right away: without a paid plan she cannot
 * book anything, so the payment is the filter. Renewing the link makes the
 * old one stop working.
 */
class EnrollmentController extends Controller
{
    use ProfileValidationRules;

    public function show(Request $request, string $token): SymfonyResponse
    {
        $studio = Studio::where('enrollment_token', $token)->first();

        if ($studio === null) {
            return Inertia::render('enrollment/unavailable')->toResponse($request)->setStatusCode(404);
        }

        $user = $request->user();

        if ($user === null) {
            // A guest who already has an account signs in and comes back.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('enrollment/show', [
            'studio' => ['name' => $studio->name, 'commune' => $studio->commune->label()],
            'state' => match (true) {
                $user === null => 'register',
                $user->roleIn($studio) !== null => 'member',
                default => 'join',
            },
            'needsAdultDeclaration' => $user?->adult_declared_at === null,
            'studioUrl' => route('studios.home', $studio),
            'joinUrl' => route('enrollment.store', $token),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ])->toResponse($request);
    }

    /**
     * Join the studio as a student, creating the account first for a guest
     * with the same rules as the registration (and its verification email).
     */
    public function store(Request $request, string $token, CreateNewUser $createNewUser, JoinStudioAsStudent $joinStudio): RedirectResponse
    {
        $studio = Studio::where('enrollment_token', $token)->firstOrFail();
        $user = $request->user();

        if ($user === null) {
            /** @var array<string, string> $input */
            $input = $request->only(['name', 'email', 'password', 'password_confirmation', 'adult']);
            $user = $createNewUser->create($input);

            event(new Registered($user));
            Auth::login($user);
        } elseif ($user->adult_declared_at === null) {
            $request->validate(['adult' => $this->adultRules()]);
            $user->forceFill(['adult_declared_at' => now()])->save();
        }

        if ($user->roleIn($studio) === null) {
            $joinStudio->handle($studio, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :studio.', ['studio' => $studio->name])]);
        }

        return to_route('studios.home', $studio);
    }
}
