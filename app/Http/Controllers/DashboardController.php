<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RememberCurrentStudio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Take the user to the home of their current studio. Someone who does
     * not belong to a studio yet sees an empty state instead.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $membership = RememberCurrentStudio::current($request);

        if ($membership === null) {
            return Inertia::render('studios/none');
        }

        return to_route('studios.home', $membership->studio);
    }
}
