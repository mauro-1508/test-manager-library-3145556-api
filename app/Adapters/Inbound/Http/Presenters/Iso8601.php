<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Presenters;

use DateTimeImmutable;
use DateTimeZone;

final class Iso8601
{
    private const UTC_FORMAT = 'Y-m-d\TH:i:s\Z';

    public static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(self::UTC_FORMAT);
    }

    public static function utcOrNull(?DateTimeImmutable $moment): ?string
    {
        return $moment === null ? null : self::utc($moment);
    }
}
