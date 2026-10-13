<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Support\BookViewAssembler;
use App\Domain\DomainValidationError;

final class UploadCoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
        private readonly BookViewAssembler $assembler,
    ) {
    }

    /**
     * @param string $detectedMimeType type detected from the content by the caller, not from the file extension
     */
    public function execute(string $bookId, string $bytes, string $detectedMimeType): BookView
    {
        $book = $this->books->findActive($bookId) ?? throw NotFound::book();
        $this->assertValidImage($bytes, $detectedMimeType);

        $previousKey = $book->coverKey();
        $book->setCover($this->storage->store($bytes, $detectedMimeType));
        $this->books->save($book);
        if ($previousKey !== null) {
            $this->storage->delete($previousKey);
        }

        return $this->assembler->assemble($book);
    }

    private function assertValidImage(string $bytes, string $mimeType): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new DomainValidationError('La portada debe ser una imagen JPG o PNG.');
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new DomainValidationError('La portada no puede estar vacía ni superar los 2 MB.');
        }
    }
}
