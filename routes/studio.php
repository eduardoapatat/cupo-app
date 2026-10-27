<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Studio\HomeController;
use App\Http\Controllers\Studio\SettingsController;
use App\Http\Controllers\Studio\TeamController;
use App\Http\Controllers\Studio\TeamInvitationController;
use App\Http\Controllers\Studio\TeamMemberController;
use App\Http\Controllers\StudioController;
use App\Http\Middleware\RememberCurrentStudio;
use Illuminate\Support\Facades\Route;

// Public: the invited person may not have an account yet. Each action
// checks the link's signature itself, to explain why a link no longer works.
Route::get('invitaciones/{invitation}', [InvitationController::class, 'show'])
    ->whereNumber('invitation')
    ->name('invitations.show');
Route::post('invitaciones/{invitation}', [InvitationController::class, 'accept'])
    ->whereNumber('invitation')
    ->name('invitations.accept');

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

            Route::get('equipo', [TeamController::class, 'index'])->name('team.index');
            Route::scopeBindings()->group(function () {
                Route::post('equipo/invitaciones', [TeamInvitationController::class, 'store'])->name('team.invitations.store');
                Route::post('equipo/invitaciones/{invitation}/reenviar', [TeamInvitationController::class, 'resend'])->name('team.invitations.resend');
                Route::delete('equipo/invitaciones/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team.invitations.destroy');
                Route::delete('equipo/{membership}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
            });
        });
});
