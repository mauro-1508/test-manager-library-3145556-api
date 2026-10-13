<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Ports\Outbound\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $moment = '2026-10-14T14:15:00Z')
    {
        $this->now = new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $moment): void
    {
        $this->now = new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }
}
