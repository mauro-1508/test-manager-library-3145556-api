<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Security;

use App\Application\Dto\TokenClaims;
use App\Application\Ports\Outbound\TokenVerifier;
use App\Domain\Role;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtTokenVerifier implements TokenVerifier
{
    public function __construct(private readonly string $secret)
    {
    }

    public function verify(string $token): ?TokenClaims
    {
        try {
            $payload = (array) JWT::decode($token, new Key($this->secret, JwtClaims::ALGORITHM));
        } catch (Exception) {
            return null;
        }

        $userId = $payload[JwtClaims::SUBJECT] ?? null;
        $role = Role::tryFrom((string) ($payload[JwtClaims::ROLE] ?? ''));

        return is_string($userId) && $userId !== '' && $role !== null ? new TokenClaims($userId, $role) : null;
    }
}
