<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            Password::sendResetLink(['email' => Str::lower($data['email'])]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'No fue posible enviar el correo en este momento. Intente nuevamente.',
            ])->onlyInput('email');
        }

        return back()->with('success', 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña. Revise también la carpeta de correo no deseado.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data,
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => $status === Password::INVALID_TOKEN
                    ? 'El enlace de recuperación es inválido o ha vencido. Solicite uno nuevo.'
                    : 'No fue posible restablecer la contraseña con los datos proporcionados.',
            ])->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('success', 'Su contraseña fue actualizada. Ya puede ingresar al sistema.');
    }
}
