<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the error page for missing pages', function () {
    $this->actingAs(User::factory()->create())
        ->get('/esta-pagina-no-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});

it('renders the error page when access is denied', function () {
    Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

    $this->get('/_test/forbidden')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 403));
});

it('renders the error page for server errors when debug mode is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/server-error', fn () => throw new RuntimeException('Boom'));

    $this->get('/_test/server-error')
        ->assertInternalServerError()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 500));
});

it('sends an expired page back with a warning', function () {
    Route::middleware('web')->post('/_test/expired', fn () => abort(419));

    $this->from('/settings/profile')
        ->post('/_test/expired')
        ->assertRedirect('/settings/profile')
        ->assertSessionHas(SessionKey::FLASH_DATA.'.toast', [
            'type' => 'warning',
            'message' => 'La página expiró. Vuelve a intentarlo.',
        ]);
});

it('keeps JSON errors for JSON requests', function () {
    $this->getJson('/esta-pagina-no-existe')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
