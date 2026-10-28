<?php

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use App\Notifications\ActivateAccount;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
});

function frontDesk(Studio $studio): User
{
    return User::factory()->memberOf($studio, StudioRole::FrontDesk)->create();
}

it('lists the students by name with their status, and not the team', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'rosa Fuentes']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    User::factory()->memberOf($studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/students')
            ->where('students.total', 3)
            ->where('students.data', fn ($students) => collect($students)->map(fn ($student) => [$student['name'], $student['status']])->all() === [
                ['Ana Pérez', 'activation_sent'],
                ['Javiera Morales', 'app'],
                ['rosa Fuentes', 'no_app'],
            ])
            ->where('enrollmentUrl', route('enrollment.show', $studio->enrollment_token)));
});

it('finds a student by name without minding case or accents, by email or by the digits of her phone', function (string $search) {
    $studio = Studio::factory()->create();
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'María Soto', 'email' => 'soto@correo.cl', 'phone' => '+56987654321']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    User::factory()->memberOf($studio, StudioRole::Instructor)->create(['name' => 'María Díaz', 'email' => 'diaz@correo.cl', 'phone' => '+56987654322']);
    User::factory()->withoutApp()->memberOf(Studio::factory()->create(), StudioRole::Student)->create(['name' => 'María Lagos', 'email' => 'lagos@correo.cl', 'phone' => '+56987654323']);

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', [$studio, 'buscar' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.data', fn ($students) => collect($students)->pluck('name')->all() === ['María Soto'])
            ->where('filters.search', $search));
})->with([
    'nombre sin tilde' => 'maria',
    'correo' => 'SOTO@correo',
    'celular con espacios' => '8765 4321',
]);

it('searches LIKE wildcards literally', function () {
    $studio = Studio::factory()->create();
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', [$studio, 'buscar' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 0));
});

it('filters by tab, with counts per tab that follow the search', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosario Vidal']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes', 'email' => 'rosa@example.com']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosalía Pérez']);
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Ana Soto']);

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', [$studio, 'buscar' => 'ros', 'estado' => 'sin-app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.data', fn ($students) => collect($students)->pluck('name')->all() === ['Rosalía Pérez'])
            ->where('counts', ['all' => 3, 'app' => 1, 'activation_sent' => 1, 'no_app' => 1])
            ->where('filters.status', 'sin-app'));
});

it('treats an unknown tab as all the students', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Student)->create();
    User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create();

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', [$studio, 'estado' => 'otra']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.total', 2)
            ->where('filters.status', null));
});

it('keeps the search and the tab in the pages', function () {
    $studio = Studio::factory()->create();
    User::factory()->count(26)->withoutApp()->memberOf($studio, StudioRole::Student)->create();

    $this->actingAs(frontDesk($studio))
        ->get(route('studios.students.index', [$studio, 'buscar' => '1234', 'estado' => 'sin-app']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.next_page_url', route('studios.students.index', [$studio, 'buscar' => '1234', 'estado' => 'sin-app', 'page' => 2])));
});

it('goes back to the filtered list after enrolling or editing', function () {
    $studio = Studio::factory()->create();
    $front = frontDesk($studio);
    $filtered = route('studios.students.index', [$studio, 'buscar' => 'rosa', 'estado' => 'sin-app']);
    $ana = User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Ana']);

    $this->actingAs($front)
        ->from($filtered)
        ->post(route('studios.students.store', $studio), ['name' => 'Rosa Fuentes', 'phone' => '987654321', 'adult' => 'on'])
        ->assertRedirect($filtered);
    $this->actingAs($front)
        ->from($filtered)
        ->put(route('studios.students.update', [$studio, $ana->memberships()->sole()]), ['name' => 'Ana Soto', 'phone' => '912345678'])
        ->assertRedirect($filtered);
});

it('enrolls at the counter a student without the app, with a normalized phone', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(frontDesk($studio))
        ->post(route('studios.students.store', $studio), ['name' => 'Rosa Fuentes', 'phone' => '9 8765-4321', 'adult' => 'on'])
        ->assertRedirect(route('studios.students.index', $studio));

    $rosa = User::where('name', 'Rosa Fuentes')->sole();
    expect($rosa->phone)->toBe('+56987654321')
        ->and($rosa->usesApp())->toBeFalse()
        ->and($rosa->email)->toBeNull()
        ->and($rosa->adult_declared_at)->not->toBeNull()
        ->and($rosa->roleIn($studio))->toBe(StudioRole::Student);
    Notification::assertNothingSent();
});

it('sends the activation link when the student has an email', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(frontDesk($studio))
        ->post(route('studios.students.store', $studio), ['name' => 'Rosa', 'phone' => '+56 9 8765 4321', 'email' => 'Rosa@Example.com', 'adult' => 'on']);

    $rosa = User::where('email', 'rosa@example.com')->sole();
    Notification::assertSentTo($rosa, ActivateAccount::class);
});

it('validates the enrollment in Spanish', function (array $data, string $field, string $message) {
    $studio = Studio::factory()->create();
    User::factory()->create(['email' => 'Carla@example.com']);

    $this->actingAs(frontDesk($studio))
        ->post(route('studios.students.store', $studio), [...['name' => 'Rosa', 'phone' => '987654321', 'adult' => 'on'], ...$data])
        ->assertSessionHasErrors([$field => $message]);

    expect($studio->memberships()->count())->toBe(1);
})->with([
    'sin celular' => [['phone' => ''], 'phone', 'El campo teléfono es obligatorio.'],
    'fijo, no celular' => [['phone' => '22 123 4567'], 'phone', 'Escribe un celular chileno, por ejemplo 9 1234 5678.'],
    'sin la declaración' => [['adult' => null], 'adult', 'Confirma que es mayor de 18 años.'],
    'correo con cuenta' => [['email' => 'carla@example.com'], 'email', 'Ese correo ya tiene una cuenta en Cupo. Compártele el enlace del estudio para que se una con ella.'],
]);

it('corrects the contact of a student without the app and sends the activation to the new email', function () {
    $studio = Studio::factory()->create();
    $rosa = User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa']);
    $membership = $rosa->memberships()->sole();

    $this->actingAs(frontDesk($studio))
        ->put(route('studios.students.update', [$studio, $membership]), ['name' => 'Rosa Fuentes', 'phone' => '912345678', 'email' => 'rosa@example.com'])
        ->assertRedirect(route('studios.students.index', $studio));

    expect($rosa->fresh()->only(['name', 'phone', 'email']))->toBe(['name' => 'Rosa Fuentes', 'phone' => '+56912345678', 'email' => 'rosa@example.com']);
    Notification::assertSentTo($rosa, ActivateAccount::class);
});

it('does not let the studio edit a student who uses the app', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['email' => 'javiera@example.com']);

    $this->actingAs(frontDesk($studio))
        ->put(route('studios.students.update', [$studio, $javiera->memberships()->sole()]), ['name' => 'Otra', 'phone' => '912345678', 'email' => 'otra@example.com'])
        ->assertForbidden();

    expect($javiera->fresh()->email)->toBe('javiera@example.com');
});

it('resends the activation link', function () {
    $studio = Studio::factory()->create();
    $rosa = User::factory()->withoutApp()->memberOf($studio, StudioRole::Student)->create(['email' => 'rosa@example.com']);

    $this->actingAs(frontDesk($studio))
        ->post(route('studios.students.activation.store', [$studio, $rosa->memberships()->sole()]))
        ->assertRedirect(route('studios.students.index', $studio));

    Notification::assertSentTo($rosa, ActivateAccount::class);
});

it('answers 404 for someone of another studio or who is not a student', function () {
    $studio = Studio::factory()->create();
    $front = frontDesk($studio);
    $elsewhere = User::factory()->withoutApp()->memberOf(Studio::factory()->create(), StudioRole::Student)->create();
    $instructor = Membership::factory()->for($studio)->role(StudioRole::Instructor)->create();

    $this->actingAs($front)
        ->put(route('studios.students.update', [$studio, $elsewhere->memberships()->sole()]), ['name' => 'X', 'phone' => '912345678'])
        ->assertNotFound();
    $this->actingAs($front)
        ->put(route('studios.students.update', [$studio, $instructor]), ['name' => 'X', 'phone' => '912345678'])
        ->assertNotFound();
});

it('keeps «Alumnas» away from instructors', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Instructor)->create())
        ->get(route('studios.students.index', $studio))
        ->assertForbidden();
});
