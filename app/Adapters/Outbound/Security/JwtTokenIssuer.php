<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\User;
use Firebase\JWT\JWT;

/**
 * HS256 access token. The payload carries only `sub` and `role` (plus the `exp` that bounds its life): no personal data.
 */
final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(private readonly string $secret)
    {
    }

    public function issue(User $user, int $validForSeconds): string
    {
        $payload = [
            JwtClaims::SUBJECT => $user->id,
            JwtClaims::ROLE => $user->role->value,
            JwtClaims::EXPIRES_AT => time() + $validForSeconds,
        ];

        return JWT::encode($payload, $this->secret, JwtClaims::ALGORITHM);
    }
}
