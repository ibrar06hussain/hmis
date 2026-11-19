<?php

declare(strict_types=1);

namespace App\Support;

final class CnicNormalizer
{
    /**
     * Strip all non-digits from a Pakistani CNIC and return 13 digits, or null if empty.
     */
    public static function normalize(?string $cnic): ?string
    {
        if ($cnic === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($cnic));

        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    public static function isValid(?string $cnic): bool
    {
        $normalized = self::normalize($cnic);

        return $normalized !== null && preg_match('/^\d{13}$/', $normalized) === 1;
    }

    /**
     * Format 13-digit CNIC as XXXXX-XXXXXXX-X.
     */
    public static function format(?string $cnic): ?string
    {
        $digits = self::normalize($cnic);

        if ($digits === null || strlen($digits) !== 13) {
            return $cnic;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
    }

    /**
     * Mask CNIC for non-privileged views (e.g., 71501-*******-3).
     */
    public static function mask(?string $cnic): ?string
    {
        $digits = self::normalize($cnic);

        if ($digits === null || strlen($digits) !== 13) {
            return null;
        }

        return substr($digits, 0, 5).'-*******-'.substr($digits, 12, 1);
    }
}
