<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

class UpdateBookRequest extends ApiRequest
{
    private const MAX_TITLE_LENGTH = 250;
    private const MAX_AUTHOR_LENGTH = 200;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:' . self::MAX_TITLE_LENGTH],
            'author' => ['required', 'string', 'max:' . self::MAX_AUTHOR_LENGTH],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer'],
        ];
    }
}
