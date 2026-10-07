<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'user@crm.test',
            'is_active' => true,
        ], $attributes));
    }

    public function test_login_screen_is_rendered_with_inertia(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('canRegister', true)
            );
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_authenticate(): void
    {
        $user = $this->activeUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_case_insensitive_for_the_email(): void
    {
        $user = $this->activeUser(['email' => 'mixed@crm.test']);

        $this->post('/login', [
            'email' => 'MiXeD@CRM.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = $this->activeUser(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->activeUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->activeUser())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_session_of_a_deactivated_account_is_terminated(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guest_can_register_and_must_verify_the_email(): void
    {
        Event::fake([Registered::class]);

        $this->post('/register', [
            'name' => 'Nueva Cuenta',
            'email' => 'nueva@crm.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'nueva@crm.test')->firstOrFail();

        $this->assertSame('nueva@crm.test', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);

        $this->assertAuthenticatedAs($user);

        // Las cuentas sin verificar no entran al área de la aplicación.
        $this->post('/logout');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/email/verify');
    }

    public function test_unverified_user_can_request_a_new_verification_link(): void
    {
        $user = $this->activeUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');
    }
}
