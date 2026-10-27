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
     * instructors). Every account uses the password "password".
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Carla Rojas',
            'email' => 'carla@example.com',
        ]);

        $studio = (new CreateStudio)->handle($owner, 'Estudio Flow', Commune::Nunoa);

        $people = [
            ['Camila Soto', 'camila@example.com', StudioRole::FrontDesk],
            ['Fernanda Díaz', 'fernanda@example.com', StudioRole::Instructor],
            ['Diego Herrera', 'diego@example.com', StudioRole::Instructor],
            ['Javiera Morales', 'javiera@example.com', StudioRole::Student],
        ];

        foreach ($people as [$name, $email, $role]) {
            User::factory()->memberOf($studio, $role)->create([
                'name' => $name,
                'email' => $email,
            ]);
        }
    }
}
