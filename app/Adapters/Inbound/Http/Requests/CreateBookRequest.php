<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class CreateBookRequest extends UpdateBookRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return parent::rules() + ['isbn' => ['required', 'string']];
    }
}
