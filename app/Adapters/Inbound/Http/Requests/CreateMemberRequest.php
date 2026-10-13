<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class CreateMemberRequest extends ApiRequest
{
    private const MAX_FULL_NAME_LENGTH = 200;
    private const MAX_DOCUMENT_LENGTH = 30;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:' . self::MAX_FULL_NAME_LENGTH],
            'document_number' => ['required', 'string', 'max:' . self::MAX_DOCUMENT_LENGTH],
        ];
    }
}
