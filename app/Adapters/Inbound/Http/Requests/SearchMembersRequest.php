<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

final class SearchMembersRequest extends PagedRequest
{
    protected function filterRules(): array
    {
        return ['q' => ['nullable', 'string']];
    }
}
