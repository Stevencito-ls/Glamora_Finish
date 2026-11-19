<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TotpService
{
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const int PERIOD = 30;

    private const int DIGITS = 6;

    public function generateSecret(int $length = 32): string
    {
        if ($length < 16) {
            throw new InvalidArgumentException('La longitud mínima del secreto Base32 es 16 caracteres.');
        }

        $secret = '';
        $maxIndex = strlen(self::BASE32_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    public function getCode(string $secret, ?int $timestamp = null): string
    {
        $key = $this->base32Decode($secret);
        $currentTimestamp = $timestamp ?? time();
        $timeSlice = intdiv($currentTimestamp, self::PERIOD);
        $binaryTime = pack('J', $timeSlice);

        $hmac = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord($hmac[19]) & 0x0F;

        $binaryCode = ((ord($hmac[$offset]) & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
            | (ord($hmac[$offset + 3]) & 0xFF);

        $otp = $binaryCode % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $normalized = preg_replace('/\s+/', '', trim($code)) ?? '';
        if (preg_match('/^\d{6}$/', $normalized) !== 1 || trim($secret) === '') {
            return false;
        }

        $currentTimestamp = $timestamp ?? time();

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->getCode($secret, $currentTimestamp + ($offset * self::PERIOD));
            if (hash_equals($candidate, $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function getProvisioningUri(string $email, string $secret, string $issuer = 'Glamora Peru'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode(trim($email));

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            rawurlencode(strtoupper(trim($secret))),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        }

        return $codes;
    }

    public function verifyAndConsumeRecoveryCode(User $user, string $inputCode): bool
    {
        $normalized = strtoupper(trim($inputCode));
        if ($normalized === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);
            if (! $lockedUser instanceof User) {
                return false;
            }

            $codes = $lockedUser->two_factor_recovery_codes;
            if (! is_array($codes) || $codes === []) {
                return false;
            }

            $matchedIndex = null;
            foreach ($codes as $index => $storedCode) {
                if (is_string($storedCode) && hash_equals(strtoupper($storedCode), $normalized)) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                return false;
            }

            unset($codes[$matchedIndex]);
            $remaining = array_values($codes);

            $lockedUser->forceFill([
                'two_factor_recovery_codes' => $remaining,
            ])->save();

            $user->setAttribute('two_factor_recovery_codes', $remaining);

            return true;
        });
    }

    private function base32Decode(string $secret): string
    {
        $cleaned = strtoupper(preg_replace('/[\s=]+/', '', $secret) ?? '');
        if ($cleaned === '') {
            throw new InvalidArgumentException('El secreto Base32 TOTP no puede estar vacío.');
        }

        $lookup = array_flip(str_split(self::BASE32_ALPHABET));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($cleaned) as $char) {
            if (! isset($lookup[$char])) {
                throw new InvalidArgumentException("Carácter Base32 inválido en el secreto TOTP: {$char}.");
            }

            $buffer = ($buffer << 5) | $lookup[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
