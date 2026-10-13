<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use App\Application\Dto\PageRequest;

/**
 * Requests with `?page=&size=`; subclasses add their own filters through filterRules().
 */
abstract class PagedRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    abstract protected function filterRules(): array;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->filterRules() + [
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:' . PageRequest::MAX_SIZE],
        ];
    }

    public function pageRequest(): PageRequest
    {
        return new PageRequest(
            $this->integer('page', PageRequest::DEFAULT_PAGE),
            $this->integer('size', PageRequest::DEFAULT_SIZE),
        );
    }
}
