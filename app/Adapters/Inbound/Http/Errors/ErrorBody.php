<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use Illuminate\Http\JsonResponse;

/**
 * The single error body of the API: {code, message, details}.
 */
final class ErrorBody
{
    /**
     * @param list<array{field: string, issue: string}> $details
     */
    public static function response(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status);
    }
}
