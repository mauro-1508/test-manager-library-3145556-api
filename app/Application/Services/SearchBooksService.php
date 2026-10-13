<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Support\BookViewAssembler;

final class SearchBooksService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookViewAssembler $assembler,
    ) {
    }

    /**
     * @return Page of BookView
     */
    public function execute(?string $text, ?string $categoryId, PageRequest $request): Page
    {
        $found = $this->books->search($text, $categoryId, $request);

        return new Page($this->assembler->assembleMany($found->items), $found->page, $found->size, $found->total);
    }
}
