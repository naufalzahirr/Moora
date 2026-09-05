<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_owner_can_login_with_username(): void
    {
        $this->seed();

        $this->post('/login', ['username' => 'owner', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs(User::where('username', 'owner')->firstOrFail());
    }
}
