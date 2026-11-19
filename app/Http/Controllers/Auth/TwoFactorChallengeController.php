<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorChallengeController extends Controller
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 300;

    public function showChallenge(): View|RedirectResponse
    {
        if (! session()->has('two_factor:user_id')) {
            return redirect()->route('login');
        }

        if ($this->isChallengeExpired()) {
            $this->clearChallengeSession();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'La sesión de verificación 2FA expiró. Inicia sesión nuevamente.']);
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TotpService $totpService): RedirectResponse
    {
        $userId = session('two_factor:user_id');
        if ($userId === null) {
            return redirect()->route('login');
        }

        if ($this->isChallengeExpired()) {
            $this->clearChallengeSession();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'La sesión de verificación 2FA expiró. Inicia sesión nuevamente.']);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:24'],
            'recovery_code' => ['nullable', 'string', 'max:24'],
        ]);

        $user = User::query()->find((int) $userId);
        if (! $user instanceof User || ! $user->is_active || ! $user->two_factor_enabled) {
            $this->clearChallengeSession();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'La sesión de verificación 2FA expiró. Inicia sesión nuevamente.']);
        }

        $throttleKey = $this->throttleKey($user->id, $request);
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            SecurityActionTriggered::dispatch(
                $user,
                'two_factor_rate_limited',
                'warning',
                "Desafío 2FA bloqueado por exceso de intentos fallidos para {$user->email}.",
                ['retry_after_seconds' => $seconds],
            );

            return back()->withErrors([
                'code' => "Demasiados intentos fallidos de verificación 2FA. Espera {$seconds} segundos e intenta nuevamente.",
            ]);
        }

        $otpCode = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));

        $verified = false;
        $usedRecovery = false;

        if ($otpCode !== '') {
            if (is_string($user->two_factor_secret) && $user->two_factor_secret !== '') {
                $verified = $totpService->verifyCode($user->two_factor_secret, $otpCode, window: 1);
            }

            if (! $verified && (str_contains($otpCode, '-') || strlen($otpCode) >= 8)) {
                $verified = $totpService->verifyAndConsumeRecoveryCode($user, $otpCode);
                $usedRecovery = $verified;
            }
        } elseif ($recoveryCode !== '') {
            $verified = $totpService->verifyAndConsumeRecoveryCode($user, $recoveryCode);
            $usedRecovery = $verified;
        }

        if (! $verified) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            SecurityActionTriggered::dispatch(
                $user,
                'two_factor_failed',
                'warning',
                "Código 2FA inválido ingresado para {$user->email}.",
            );

            return back()->withErrors([
                'code' => 'El código TOTP o código de recuperación ingresado es inválido o ha expirado.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) session('two_factor:remember', false);
        $this->clearChallengeSession();

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        SecurityActionTriggered::dispatch(
            $user,
            $usedRecovery ? 'two_factor_recovery_used' : 'two_factor_verified',
            $usedRecovery ? 'warning' : 'info',
            $usedRecovery
                ? "Acceso completado mediante código de recuperación 2FA para {$user->email}."
                : "Verificación 2FA TOTP completada exitosamente para {$user->email}.",
        );

        return redirect()->intended(route('account.index'));
    }

    private function isChallengeExpired(): bool
    {
        $expiresAt = session('two_factor:expires_at');

        return ! is_numeric($expiresAt) || now()->timestamp > (int) $expiresAt;
    }

    private function clearChallengeSession(): void
    {
        session()->forget(['two_factor:user_id', 'two_factor:remember', 'two_factor:expires_at']);
    }

    private function throttleKey(int $userId, Request $request): string
    {
        return 'two-factor:' . $userId . '|' . $request->ip();
    }
}
