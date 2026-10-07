<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'user@crm.test',
            'is_active' => true,
        ], $attributes));
    }

    public function test_user_can_open_their_profile(): void
    {
        $this->actingAs($this->user())
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->where('mustVerifyEmail', true)
            );
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Actualizado',
                'email' => 'actualizado@crm.test',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Nombre Actualizado', $user->name);
        $this->assertSame('actualizado@crm.test', $user->email);
    }

    public function test_changing_the_email_requires_verification_again(): void
    {
        $user = $this->user(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'otro@crm.test',
            ])
            ->assertRedirect();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = $this->user();
        User::factory()->create(['email' => 'ocupado@crm.test']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'ocupado@crm.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_change_their_password(): void
    {
        $user = $this->user();
        $originalHash = $user->password;

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'clave-nueva-123',
            ])
            ->assertRedirect();

        $this->assertNotSame($originalHash, $user->refresh()->password);
        $this->assertTrue(Hash::check('clave-nueva-123', $user->password));
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'clave-incorrecta',
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'clave-nueva-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_password_change_requires_a_matching_confirmation(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'otra-clave-123',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_open_the_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }
}
