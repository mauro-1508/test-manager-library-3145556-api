<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User;

final class InMemoryUsers implements UserRepository
{
    /** @var array<string, User> keyed by username */
    private array $byUsername = [];

    public function save(User $user): void
    {
        $this->byUsername[$user->username] = $user;
    }

    public function findByUsername(string $normalizedUsername): ?User
    {
        return $this->byUsername[$normalizedUsername] ?? null;
    }

    public function count(): int
    {
        return count($this->byUsername);
    }
}
