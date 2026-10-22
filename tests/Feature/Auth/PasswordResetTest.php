<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status', 'Te enviamos un enlace para restablecer tu contraseña. Revisa tu correo y la carpeta de spam.');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_email_is_in_spanish(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $html = $mail->render();

            $this->assertSame('Recupera el acceso a tu cuenta UEBTV', $mail->subject);
            $this->assertSame('Cambiar mi contraseña', $mail->actionText);
            $this->assertStringContainsString('Recibimos una solicitud para cambiar la contraseña', $html);
            $this->assertStringContainsString('Si no solicitaste este cambio', $html);
            $this->assertStringContainsString('Hola:', $html);
            $this->assertStringContainsString('Atentamente,', $html);
            $this->assertStringContainsString('copia y pega este enlace en tu navegador', $html);
            $this->assertStringNotContainsString('You are receiving this email', $html);
            $this->assertStringNotContainsString("If you're having trouble", $html);

            return true;
        });
    }

    public function test_password_reset_validation_is_in_spanish(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'correo-invalido']);

        $response->assertSessionHasErrors(['email' => 'el correo electrónico debe ser una dirección de correo válida.']);
    }

    public function test_unknown_email_message_is_in_spanish(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'desconocido@example.com']);

        $response->assertSessionHasErrors(['email' => 'No encontramos una cuenta con ese correo electrónico.']);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
