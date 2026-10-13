<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use Illuminate\Database\QueryException;

final class PgErrors
{
    private const UNIQUE_VIOLATION = '23505';
    private const CHECK_VIOLATION = '23514';

    public static function isUniqueViolation(QueryException $error): bool
    {
        return ($error->errorInfo[0] ?? $error->getCode()) === self::UNIQUE_VIOLATION;
    }

    public static function isCheckViolation(QueryException $error): bool
    {
        return ($error->errorInfo[0] ?? $error->getCode()) === self::CHECK_VIOLATION;
    }
}
