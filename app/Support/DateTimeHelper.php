<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class DateTimeHelper
{
    public static function displayTimezone(): string
    {
        return (string) config('hmis.display_timezone', 'Asia/Karachi');
    }

    public static function formatDateTime(DateTimeInterface|string|null $value, string $format = 'd M Y, h:i A'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $carbon = $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC');

        return $carbon->setTimezone(self::displayTimezone())->format($format).' PKT';
    }

    public static function formatDate(DateTimeInterface|string|null $value, string $format = 'd M Y'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $carbon = $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);

        return $carbon->format($format);
    }

    public static function nowDisplay(string $format = 'd M Y, h:i A'): string
    {
        return CarbonImmutable::now('UTC')->setTimezone(self::displayTimezone())->format($format).' PKT';
    }
}
