<?php

namespace Database\Seeders;

use App\Actions\Studios\CreateStudio;
use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStudioSeeder extends Seeder
{
    /**
     * Estudio Flow, the fictional studio of the docs, created by its owner
     * the way the app does it, with one demo account per role (two
     * instructors) and Rosa, who pays in cash and does not use the app.
     * Fernanda also teaches at Estudio Sur, so the studio switcher has
     * something to switch. Every account uses the password "password".
     */
    public function run(): void
    {
        $createStudio = new CreateStudio;

        $carla = User::factory()->create(['name' => 'Carla Rojas', 'email' => 'carla@example.com']);
        $flow = $createStudio->handle($carla, 'Estudio Flow', Commune::Nunoa);

        $people = [
            ['Camila Soto', 'camila@example.com', StudioRole::FrontDesk],
            ['Fernanda Díaz', 'fernanda@example.com', StudioRole::Instructor],
            ['Diego Herrera', 'diego@example.com', StudioRole::Instructor],
            ['Javiera Morales', 'javiera@example.com', StudioRole::Student],
        ];

        foreach ($people as [$name, $email, $role]) {
            User::factory()->memberOf($flow, $role)->create([
                'name' => $name,
                'email' => $email,
            ]);
        }

        User::factory()->withoutApp()->memberOf($flow, StudioRole::Student)->create([
            'name' => 'Rosa Fuentes',
            'phone' => '+56987654321',
        ]);

        $paula = User::factory()->create(['name' => 'Paula Vidal', 'email' => 'paula@example.com']);
        $sur = $createStudio->handle($paula, 'Estudio Sur', Commune::LaFlorida);

        User::where('email', 'fernanda@example.com')->sole()
            ->memberships()->create(['studio_id' => $sur->id, 'role' => StudioRole::Instructor]);
    }
}
