<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_web_session_redirects_to_login(): void
    {
        Route::middleware('web')->get('/testing/expired-session', function (): never {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $user = User::factory()->create(['role' => 'reporter']);

        $this->actingAs($user)
            ->get('/testing/expired-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Su sesión expiró. Ingrese nuevamente para continuar.');

        $this->assertGuest();
    }

    public function test_expired_json_session_keeps_a_419_json_response(): void
    {
        Route::middleware('web')->get('/testing/expired-json-session', function (): never {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $this->getJson('/testing/expired-json-session')
            ->assertStatus(419)
            ->assertJson(['message' => 'La sesión expiró. Ingrese nuevamente.']);
    }
}
