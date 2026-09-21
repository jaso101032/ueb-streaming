<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Mínimo 8 caracteres');
        $response->assertSee('Al menos una letra mayúscula');
        $response->assertSee('Al menos una letra minúscula');
        $response->assertSee('Al menos un número');
        $response->assertSee('Al menos un símbolo');
    }

    public function test_passwords_missing_a_requirement_are_rejected(): void
    {
        $passwords = [
            'Ab1!', // Demasiado corta.
            'segura123!', // Sin mayúscula.
            'SEGURA123!', // Sin minúscula.
            'Seguraabc!', // Sin número.
            'Segura123', // Sin símbolo.
            'Segura 123', // Un espacio no sustituye un símbolo.
            '12345678!', // Sin letras.
        ];

        foreach ($passwords as $password) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');

            $this->assertGuest();
            $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        }
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Segura123!',
            'password_confirmation' => 'OtraSegura123!',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
