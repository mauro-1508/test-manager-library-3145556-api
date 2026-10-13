<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Security;

final class JwtClaims
{
    public const ALGORITHM = 'HS256';
    public const SUBJECT = 'sub';
    public const ROLE = 'role';
    public const EXPIRES_AT = 'exp';
}
