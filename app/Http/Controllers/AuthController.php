<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function createLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = array_merge(
            $request->safe()->only(['email', 'password']),
            ['is_active' => true],
        );
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if (User::where('email', $request->string('email'))->where('is_active', false)->exists()) {
                return back()->withErrors(['email' => 'Su cuenta está inactiva. Contacte al Administrador.'])->onlyInput('email');
            }

            return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function createRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Cuenta creada. Ya puede registrar actividades.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }
}
