<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Studio\HomeController;
use App\Http\Middleware\RememberCurrentStudio;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inicio', DashboardController::class)->name('dashboard');

    Route::middleware(RememberCurrentStudio::class)
        ->prefix('estudios/{studio}')
        ->can('view', 'studio')
        ->name('studios.')
        ->group(function () {
            Route::get('/', HomeController::class)->name('home');
        });
});
