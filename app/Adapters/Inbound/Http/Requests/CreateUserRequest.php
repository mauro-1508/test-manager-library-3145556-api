<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class CreateUserRequest extends ApiRequest
{
    private const MAX_USERNAME_LENGTH = 120;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:' . self::MAX_USERNAME_LENGTH],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ];
    }
}
