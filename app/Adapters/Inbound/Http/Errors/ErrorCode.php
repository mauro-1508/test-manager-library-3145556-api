<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

/**
 * Codes raised by the HTTP adapter itself (the business ones live in the domain and application errors).
 */
final class ErrorCode
{
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const FORBIDDEN_ROLE = 'FORBIDDEN_ROLE';
    public const NOT_FOUND = 'NOT_FOUND';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    public const INTERNAL_ERROR = 'INTERNAL_ERROR';
}
