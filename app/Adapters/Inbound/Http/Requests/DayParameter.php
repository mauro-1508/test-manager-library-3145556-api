<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

/**
 * Reads a calendar-day query parameter (YYYY-MM-DD) as midnight UTC.
 */
final class DayParameter
{
    public const FORMAT = 'Y-m-d';

    public static function read(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $request->query($name);
        if ($value === null || $value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat('!' . self::FORMAT, (string) $value, new DateTimeZone('UTC')) ?: null;
    }
}
