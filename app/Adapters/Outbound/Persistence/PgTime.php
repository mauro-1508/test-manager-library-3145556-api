<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Conversion between domain instants and timestamptz values: always UTC (D-05).
 */
final class PgTime
{
    public static function toDb(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromDb(string $value): DateTimeImmutable
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function fromDbNullable(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::fromDb($value);
    }

    /**
     * Start (00:00 UTC) of the calendar day of the instant.
     */
    public static function startOfDay(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
    }

    /**
     * Exclusive upper bound of an inclusive calendar day: 00:00 UTC of the next day.
     */
    public static function startOfNextDay(DateTimeImmutable $instant): DateTimeImmutable
    {
        return self::startOfDay($instant)->modify('+1 day');
    }

    /**
     * Escapes LIKE wildcards so user text is matched literally.
     */
    public static function containsPattern(string $text): string
    {
        return '%' . addcslashes($text, '\\%_') . '%';
    }
}
