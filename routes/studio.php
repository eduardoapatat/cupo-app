<?php

use App\Http\Controllers\AccountActivationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Studio\ClassesController;
use App\Http\Controllers\Studio\ClassTypeController;
use App\Http\Controllers\Studio\EnrollmentLinkController;
use App\Http\Controllers\Studio\HomeController;
use App\Http\Controllers\Studio\ScheduleSlotController;
use App\Http\Controllers\Studio\SettingsController;
use App\Http\Controllers\Studio\StudentActivationController;
use App\Http\Controllers\Studio\StudentController;
use App\Http\Controllers\Studio\StudioRulesController;
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

// Public: the studio's enrollment link, shared by its owner.
Route::get('inscripcion/{token}', [EnrollmentController::class, 'show'])->name('enrollment.show');
Route::post('inscripcion/{token}', [EnrollmentController::class, 'store'])->name('enrollment.store');

// Public: a student enrolled at the counter activates her account from the
// signed link in her email.
Route::get('activar-cuenta/{user}', [AccountActivationController::class, 'show'])
    ->whereNumber('user')
    ->name('activation.show');
Route::post('activar-cuenta/{user}', [AccountActivationController::class, 'store'])
    ->whereNumber('user')
    ->name('activation.store');

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
            Route::put('estudio/enlace', [EnrollmentLinkController::class, 'update'])->name('settings.enrollment-link.update');
            Route::put('estudio/reglas', [StudioRulesController::class, 'update'])->name('settings.rules.update');

            Route::get('equipo', [TeamController::class, 'index'])->name('team.index');
            Route::scopeBindings()->group(function () {
                Route::post('equipo/invitaciones', [TeamInvitationController::class, 'store'])->name('team.invitations.store');
                Route::post('equipo/invitaciones/{invitation}/reenviar', [TeamInvitationController::class, 'resend'])->name('team.invitations.resend');
                Route::delete('equipo/invitaciones/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team.invitations.destroy');
                Route::delete('equipo/{membership}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');

                Route::get('alumnas', [StudentController::class, 'index'])->name('students.index');
                Route::post('alumnas', [StudentController::class, 'store'])->name('students.store');
                Route::get('alumnas/{membership}', [StudentController::class, 'show'])->name('students.show');
                Route::put('alumnas/{membership}', [StudentController::class, 'update'])->name('students.update');
                Route::post('alumnas/{membership}/activacion', [StudentActivationController::class, 'store'])->name('students.activation.store');

                Route::get('clases', [ClassesController::class, 'index'])->name('classes.index');
                Route::post('clases/tipos', [ClassTypeController::class, 'store'])->name('classes.types.store');
                Route::put('clases/tipos/{classType}', [ClassTypeController::class, 'update'])->name('classes.types.update');
                Route::post('clases/horario', [ScheduleSlotController::class, 'store'])->name('classes.slots.store');
                Route::put('clases/horario/{scheduleSlot}', [ScheduleSlotController::class, 'update'])->name('classes.slots.update');
                Route::delete('clases/horario/{scheduleSlot}', [ScheduleSlotController::class, 'destroy'])->name('classes.slots.destroy');
            });
        });
});
