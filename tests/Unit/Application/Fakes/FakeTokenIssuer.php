<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\User;

final class FakeTokenIssuer implements TokenIssuer
{
    public ?int $lastValidity = null;

    public function issue(User $user, int $validForSeconds): string
    {
        $this->lastValidity = $validForSeconds;

        return "token:{$user->id}:{$user->role->value}";
    }
}
