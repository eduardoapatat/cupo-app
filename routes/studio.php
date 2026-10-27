<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Studio\HomeController;
use App\Http\Controllers\Studio\SettingsController;
use App\Http\Controllers\StudioController;
use App\Http\Middleware\RememberCurrentStudio;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inicio', DashboardController::class)->name('dashboard');

    // Before the studio group, so /estudios/crear never reads as a slug.
    Route::resource('estudios', StudioController::class)
        ->only(['create', 'store'])
        ->names('studios');

    Route::middleware(RememberCurrentStudio::class)
        ->prefix('estudios/{studio}')
        ->can('view', 'studio')
        ->name('studios.')
        ->group(function () {
            Route::get('/', HomeController::class)->name('home');

            Route::get('estudio', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('estudio', [SettingsController::class, 'update'])->name('settings.update');
        });
});
