<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use App\Domain\DomainError;

/**
 * The only place that knows which HTTP status each business code travels with (api-contract §2).
 */
final class BusinessErrorStatus
{
    private const STATUS_BY_CODE = [
        ErrorCode::INVALID_CREDENTIALS => 401,
        ErrorCode::FORBIDDEN_ROLE => 403,
        ErrorCode::NOT_FOUND => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        ErrorCode::VALIDATION_ERROR => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    /**
     * @return int|null null when the code is not part of the contract
     */
    public static function of(DomainError $error): ?int
    {
        return self::STATUS_BY_CODE[$error->errorCode()] ?? null;
    }
}
