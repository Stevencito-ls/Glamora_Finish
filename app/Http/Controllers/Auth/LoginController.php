<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    private const int MAX_LOGIN_ATTEMPTS = 5;

    private const int LOCKOUT_SECONDS = 60;

    private const int TWO_FACTOR_TTL_MINUTES = 10;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $remember = $request->boolean('remember');
        $throttleKey = $this->throttleKey($email, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $user = User::query()->where('email', $email)->first();

            SecurityActionTriggered::dispatch(
                $user,
                'auth_rate_limited',
                'warning',
                "Inicio de sesión bloqueado temporalmente por exceso de intentos para {$email}.",
                ['email' => $email, 'retry_after_seconds' => $seconds],
            );

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => "Demasiados intentos fallidos. Por seguridad, intenta nuevamente en {$seconds} segundos.",
                ]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);
            event(new Failed('web', $user, ['email' => $email]));

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
                ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            SecurityActionTriggered::dispatch(
                $user,
                'login_inactive_blocked',
                'warning',
                "Intento de acceso bloqueado para cuenta inactiva: {$user->email}.",
                ['email' => $user->email],
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Tu cuenta se encuentra desactivada. Contáctanos para reactivar el acceso.',
                ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->two_factor_enabled && is_string($user->two_factor_secret) && $user->two_factor_secret !== '') {
            $request->session()->regenerate();

            session([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => $remember,
                'two_factor:expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES)->timestamp,
            ]);

            SecurityActionTriggered::dispatch(
                $user,
                'two_factor_challenge_issued',
                'info',
                "Desafío 2FA TOTP solicitado para {$user->email}.",
            );

            return redirect()->route('two-factor.challenge');
        }

        session()->forget(['two_factor:user_id', 'two_factor:remember', 'two_factor:expires_at']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('account.index'));
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login:' . Str::transliterate(Str::lower($email) . '|' . $request->ip());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('cart_success', 'Sesión cerrada correctamente.');
    }
}
