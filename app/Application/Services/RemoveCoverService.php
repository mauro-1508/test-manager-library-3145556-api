<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Support\BookViewAssembler;

final class RemoveCoverService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
        private readonly BookViewAssembler $assembler,
    ) {
    }

    public function execute(string $bookId): BookView
    {
        $book = $this->books->findActive($bookId) ?? throw NotFound::book();
        $key = $book->coverKey();
        if ($key !== null) {
            // Data-model section 7: clear the key and persist first, delete the binary afterwards.
            $book->removeCover();
            $this->books->save($book);
            $this->storage->delete($key);
        }

        return $this->assembler->assemble($book);
    }
}
