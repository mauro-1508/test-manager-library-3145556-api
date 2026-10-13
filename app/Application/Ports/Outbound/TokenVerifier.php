<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\TokenClaims;

interface TokenVerifier
{
    /**
     * @return TokenClaims|null null when the token is malformed, tampered with or expired
     */
    public function verify(string $token): ?TokenClaims;
}
