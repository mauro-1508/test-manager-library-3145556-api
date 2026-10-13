<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class LendBooksRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array'],
            'items.*.book_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return list<string>
     */
    public function bookIds(): array
    {
        return array_values(array_map(
            static fn (array $item): string => $item['book_id'],
            $this->validated('items'),
        ));
    }
}
