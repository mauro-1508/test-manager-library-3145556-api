<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Ports\Outbound\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    private const PREFIX = 'hashed:';

    public function hash(string $plainPassword): string
    {
        return self::PREFIX . strrev($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $this->hash($plainPassword) === $hash;
    }
}
