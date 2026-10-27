<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the studio home. The studio, the member's role and the menu come
     * as shared props (see HandleInertiaRequests).
     */
    public function __invoke(Studio $studio): Response
    {
        return Inertia::render('studios/home');
    }
}
