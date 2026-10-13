<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Support\BookViewAssembler;

final class GetBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookViewAssembler $assembler,
    ) {
    }

    public function execute(string $bookId): BookView
    {
        return $this->assembler->assemble($this->books->findActive($bookId) ?? throw NotFound::book());
    }
}
