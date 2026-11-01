<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_opens_the_home_screen()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_root_sends_guests_to_the_login()
    {
        $this->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login'));
    }
}
