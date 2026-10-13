<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class LoginRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
