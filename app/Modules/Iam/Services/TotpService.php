<?php

declare(strict_types=1);

namespace App\Modules\Iam\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pure-PHP Offline RFC 6238 TOTP Implementation (Zero External Dependencies).
 * Compatible with Google Authenticator, Microsoft Authenticator, Aegis, and FreeOTP on LAN-only workstations.
 */
final class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD_SECONDS = 30;

    private const DIGITS = 6;

    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes($length);
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[ord($bytes[$i]) & 31];
        }

        return $secret;
    }

    public function provisioningUri(string $accountLabel, string $secret, ?string $issuer = null): string
    {
        $issuerName = $issuer ?? (string) config('hmis.facility_name', 'Cardiac Hospital Gilgit');
        $label = rawurlencode($issuerName.':'.$accountLabel);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            rawurlencode($secret),
            rawurlencode($issuerName),
            self::DIGITS,
            self::PERIOD_SECONDS,
        );
    }

    public function currentCode(string $base32Secret, ?int $timestamp = null): string
    {
        $timeSlice = (int) floor(($timestamp ?? time()) / self::PERIOD_SECONDS);

        return $this->codeForSlice($base32Secret, $timeSlice);
    }

    /**
     * Verify a 6-digit TOTP code allowing +/- $window 30-second clock drift steps.
     */
    public function verifyCode(string $base32Secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', trim($code)) ?? '';
        if (strlen($normalizedCode) !== self::DIGITS) {
            return false;
        }

        $currentSlice = (int) floor(($timestamp ?? time()) / self::PERIOD_SECONDS);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->codeForSlice($base32Secret, $currentSlice + $offset);
            if (hash_equals($expected, $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate single-use plain recovery codes and their hashed storage array.
     *
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * Validate and consume a single-use recovery code, returning remaining hashed codes or null if invalid.
     *
     * @param  list<string>  $hashedCodes
     * @return list<string>|null
     */
    public function consumeRecoveryCode(string $inputCode, array $hashedCodes): ?array
    {
        $normalized = strtoupper(trim($inputCode));

        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashedCodes[$index]);

                return array_values($hashedCodes);
            }
        }

        return null;
    }

    private function codeForSlice(string $base32Secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($base32Secret);
        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $binaryTime, $secretKey, true);
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $value = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($clean); $i < $len; $i++) {
            $val = strpos(self::BASE32_ALPHABET, $clean[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
