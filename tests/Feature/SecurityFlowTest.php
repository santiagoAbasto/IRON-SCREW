<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_navigation_and_logout_flow(): void
    {
        $role = Role::create([
            'name' => 'Administrador',
            'permissions' => ['orders.view', 'settings.view'],
        ]);
        $user = User::factory()->create([
            'username' => 'admin-e2e',
            'password' => 'ClaveSegura123',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->get(route('orders.index'))->assertRedirect(route('login'));

        $this->post(route('login.submit'), [
            'usuario' => $user->username,
            'password' => 'ClaveSegura123',
        ])->assertRedirect(route('orders.index'));

        $this->get(route('orders.index'))->assertOk();
        $this->get(route('settings.index'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_logout_cannot_be_triggered_with_get(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.submit'), [
                'usuario' => 'inexistente',
                'password' => 'incorrecta',
            ])->assertSessionHasErrors('usuario');
        }

        $this->post(route('login.submit'), [
            'usuario' => 'inexistente',
            'password' => 'incorrecta',
        ])->assertTooManyRequests();
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_new_users_require_a_strong_password(): void
    {
        $role = Role::create([
            'name' => 'Administrador',
            'permissions' => ['settings.view', 'users.manage'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->withSession(['iron_user' => $admin->id])
            ->post(route('settings.users.store'), [
                'name' => 'Usuario Débil',
                'username' => 'debil',
                'email' => 'debil@example.com',
                'password' => '123456',
                'role_id' => $role->id,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'debil']);
    }
}
