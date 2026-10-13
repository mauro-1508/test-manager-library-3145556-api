<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use Illuminate\Validation\ValidationException;

/**
 * Turns the failed rules of a Laravel validator into the `details` list: [{field, issue}] with Spanish issues.
 */
final class ValidationIssues
{
    private const DEFAULT_ISSUE = 'no es válido';

    private const ISSUE_BY_RULE = [
        'Required' => 'es obligatorio',
        'String' => 'debe ser texto',
        'Integer' => 'debe ser un número entero',
        'Array' => 'debe ser una lista',
        'Uuid' => 'debe ser un identificador válido',
        'Min' => 'es menor que el mínimo permitido',
        'Max' => 'supera el máximo permitido',
        'DateFormat' => 'debe ser una fecha con formato AAAA-MM-DD',
        'In' => 'no es un valor permitido',
    ];

    /**
     * @return list<array{field: string, issue: string}>
     */
    public static function of(ValidationException $exception): array
    {
        $details = [];
        foreach ($exception->validator->failed() as $field => $rules) {
            foreach (array_keys($rules) as $rule) {
                $details[] = ['field' => (string) $field, 'issue' => self::ISSUE_BY_RULE[$rule] ?? self::DEFAULT_ISSUE];
            }
        }

        return $details;
    }
}
