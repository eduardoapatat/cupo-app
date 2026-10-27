<?php

namespace Database\Seeders;

use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStudioSeeder extends Seeder
{
    /**
     * Estudio Flow, the fictional studio of the docs, with one demo account
     * per role (two instructors). Every account uses the password "password".
     */
    public function run(): void
    {
        $studio = Studio::create([
            'name' => 'Estudio Flow',
            'commune' => Commune::Nunoa,
            ...Studio::defaultRules(),
        ]);

        $people = [
            ['Carla Rojas', 'carla@example.com', StudioRole::Owner],
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
