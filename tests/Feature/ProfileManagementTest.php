<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_and_update_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre anterior',
            'role' => 'reporter',
            'password' => 'password-anterior',
        ]);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee($user->email)
            ->assertSee('href="'.route('profile.show').'"', false);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nombre actualizado',
            'current_password' => 'password-anterior',
            'password' => 'password-nueva',
            'password_confirmation' => 'password-nueva',
        ])->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Nombre actualizado', $user->name);
        $this->assertTrue(Hash::check('password-nueva', $user->password));
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['role' => 'reporter', 'password' => 'password-anterior']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'current_password' => 'password-incorrecta',
            'password' => 'password-nueva',
            'password_confirmation' => 'password-nueva',
        ])->assertSessionHasErrors(['current_password']);

        $this->assertTrue(Hash::check('password-anterior', $user->fresh()->password));
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }
}
