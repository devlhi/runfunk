<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Peserta Lupa',
            'email' => 'lupa@example.com',
            'password' => 'rahasia12345',
            'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    public function test_halaman_lupa_kata_sandi_terbuka(): void
    {
        $this->get('/lupa-kata-sandi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_tautan_atur_ulang_dikirim_ke_email_terdaftar(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/lupa-kata-sandi', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_email_tak_terdaftar_tidak_membocorkan_keberadaan_akun(): void
    {
        Notification::fake();

        $response = $this->post('/lupa-kata-sandi', ['email' => 'tidakada@example.com']);

        $response->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_kata_sandi_bisa_diatur_ulang_dengan_token_sah(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/lupa-kata-sandi', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/atur-ulang-kata-sandi', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'sandibaru12345',
                'password_confirmation' => 'sandibaru12345',
            ])->assertRedirect('/masuk');

            return true;
        });

        $this->assertTrue(
            auth()->validate(['email' => $user->email, 'password' => 'sandibaru12345'])
        );
    }

    public function test_token_palsu_ditolak(): void
    {
        $user = $this->user();

        $this->post('/atur-ulang-kata-sandi', [
            'token' => 'token-karangan-penyerang',
            'email' => $user->email,
            'password' => 'sandibaru12345',
            'password_confirmation' => 'sandibaru12345',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(
            auth()->validate(['email' => $user->email, 'password' => 'rahasia12345']),
            'Kata sandi lama harus tetap berlaku'
        );
    }

    public function test_header_keamanan_terpasang(): void
    {
        $response = $this->get('/');

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }
}
