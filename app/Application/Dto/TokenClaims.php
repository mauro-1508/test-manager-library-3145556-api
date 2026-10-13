<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Role;

/**
 * What a valid access token says about its bearer: nothing but the user id and the role.
 */
final readonly class TokenClaims
{
    public function __construct(public string $userId, public Role $role)
    {
    }
}
