<?php

declare(strict_types=1);

namespace App\Domain;

final class LendingPolicy
{
    public const MAX_UNRETURNED_PER_MEMBER = 3;

    public function assertCanBorrow(int $unreturnedCount, bool $hasOverdue, int $requested): void
    {
        if ($hasOverdue) {
            throw new MemberHasOverdue();
        }
        if ($unreturnedCount + $requested > self::MAX_UNRETURNED_PER_MEMBER) {
            throw new MemberLimitExceeded($unreturnedCount, self::MAX_UNRETURNED_PER_MEMBER);
        }
    }
}
