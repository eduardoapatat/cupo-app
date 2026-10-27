<?php

namespace App\Http\Controllers;

use App\Actions\Studios\CreateStudio;
use App\Enums\Commune;
use App\Http\Requests\StudioRequest;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Show the form to create the user's studio.
     */
    #[Authorize('create', Studio::class)]
    public function create(): Response
    {
        return Inertia::render('studios/create', [
            'communes' => Commune::options(),
        ]);
    }

    /**
     * Create the studio, with the user as its owner, and take them to it.
     */
    #[Authorize('create', Studio::class)]
    public function store(StudioRequest $request, #[CurrentUser] User $user, CreateStudio $createStudio): RedirectResponse
    {
        $studio = $createStudio->handle($user, $request->studioName(), $request->commune());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your studio is ready.')]);

        return to_route('studios.home', $studio);
    }
}
