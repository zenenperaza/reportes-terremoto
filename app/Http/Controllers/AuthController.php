<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\EmailTwoFactorCode;
use App\Notifications\EmailTwoFactorCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function createLogin(Request $request): View
    {
        $this->clearTwoFactorSession($request);

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = array_merge(
            $request->safe()->only(['email', 'password']),
            ['is_active' => true],
        );
        if (! Auth::validate($credentials)) {
            if (User::where('email', $request->string('email'))->where('is_active', false)->exists()) {
                return back()->withErrors(['email' => "Su cuenta est\u{00E1} inactiva. Contacte al Administrador."])->onlyInput('email');
            }

            return back()->withErrors(['email' => "Las credenciales no son v\u{00E1}lidas."])->onlyInput('email');
        }

        $user = User::where('email', $request->string('email'))->firstOrFail();

        if ($user->requires_two_factor) {
            $request->session()->regenerate();

            try {
                $this->issueTwoFactorCode($request, $user, $request->boolean('remember'));
            } catch (Throwable $exception) {
                report($exception);
                $this->clearTwoFactorSession($request);

                return back()->withErrors([
                    'email' => 'No fue posible enviar el código de acceso. Intente nuevamente o contacte al administrador.',
                ])->onlyInput('email');
            }

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function createTwoFactorChallenge(Request $request): View|RedirectResponse
    {
        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            $this->clearTwoFactorSession($request);

            return redirect()->route('login')->with('error', 'El proceso de verificación venció. Ingrese nuevamente.');
        }

        return view('auth.two-factor-challenge', [
            'maskedEmail' => $this->maskEmail($challenge->user->email),
        ]);
    }

    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], ['code.digits' => 'Ingrese el código completo de 6 dígitos.']);
        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            $this->clearTwoFactorSession($request);

            return redirect()->route('login')->with('error', 'El código venció. Ingrese nuevamente para solicitar otro.');
        }

        $challenge->increment('attempts');

        if (! hash_equals($challenge->code_hash, $this->hashTwoFactorCode($data['code']))) {
            if ($challenge->attempts >= 5) {
                $challenge->update(['consumed_at' => now()]);
                $this->clearTwoFactorSession($request);

                return redirect()->route('login')->with('error', 'Se agotaron los intentos de verificación. Ingrese nuevamente.');
            }

            return back()->withErrors(['code' => 'El código ingresado no es correcto.']);
        }

        $challenge->update(['consumed_at' => now()]);
        Auth::login($challenge->user, filter_var(
            $request->session()->get('two_factor_remember', false),
            FILTER_VALIDATE_BOOL,
        ));
        $this->clearTwoFactorSession($request);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resendTwoFactorCode(Request $request): RedirectResponse
    {
        $challenge = $this->pendingChallenge($request, allowExpired: true);

        if (! $challenge || ! $challenge->user->is_active || ! $challenge->user->requires_two_factor) {
            $this->clearTwoFactorSession($request);

            return redirect()->route('login')->with('error', 'El proceso de verificación venció. Ingrese nuevamente.');
        }

        if ($challenge->created_at->greaterThan(now()->subMinute())) {
            return back()->withErrors(['code' => 'Espere un minuto antes de solicitar otro código.']);
        }

        try {
            $this->issueTwoFactorCode($request, $challenge->user, filter_var(
                $request->session()->get('two_factor_remember', false),
                FILTER_VALIDATE_BOOL,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['code' => 'No fue posible reenviar el código. Intente nuevamente.']);
        }

        return back()->with('success', 'Se envió un nuevo código a su correo electrónico.');
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

        return redirect()->route('login')->with('success', "Sesi\u{00F3}n cerrada correctamente.");
    }

    private function issueTwoFactorCode(Request $request, User $user, bool $remember): void
    {
        EmailTwoFactorCode::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = EmailTwoFactorCode::create([
            'user_id' => $user->id,
            'code_hash' => $this->hashTwoFactorCode($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $request->session()->put([
            'two_factor_challenge_id' => $challenge->id,
            'two_factor_user_id' => $user->id,
            'two_factor_remember' => $remember,
        ]);

        try {
            $user->notify(new EmailTwoFactorCodeNotification($code));
        } catch (Throwable $exception) {
            $challenge->delete();
            throw $exception;
        }
    }

    private function pendingChallenge(Request $request, bool $allowExpired = false): ?EmailTwoFactorCode
    {
        return EmailTwoFactorCode::query()
            ->with('user')
            ->whereKey($request->session()->get('two_factor_challenge_id'))
            ->where('user_id', $request->session()->get('two_factor_user_id'))
            ->whereNull('consumed_at')
            ->when(! $allowExpired, fn ($query) => $query->where('expires_at', '>', now()))
            ->first();
    }

    private function hashTwoFactorCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function clearTwoFactorSession(Request $request): void
    {
        $request->session()->forget(['two_factor_challenge_id', 'two_factor_user_id', 'two_factor_remember']);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = Str::substr($name, 0, min(2, Str::length($name)));

        return $visible.str_repeat('*', max(3, Str::length($name) - Str::length($visible))).'@'.$domain;
    }
}
