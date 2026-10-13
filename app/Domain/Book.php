<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class Book
{
    public function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        public readonly Isbn $isbn,
        private string $categoryId,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey = null,
        private ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    public static function create(
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
    ): self {
        return new self(
            Uuid::v4(),
            self::requireText($title, 'El título es obligatorio.'),
            self::requireText($author, 'El autor es obligatorio.'),
            $isbn,
            $categoryId,
            $totalCopies->value,
            $totalCopies->value,
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function withdraw(): void
    {
        if ($this->availableCopies <= 0) {
            throw new BookNotAvailable($this->title);
        }
        $this->availableCopies--;
    }

    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new DomainValidationError('Los ejemplares disponibles no pueden superar el total.');
        }
        $this->availableCopies++;
    }

    public function changeTotal(Quantity $newTotal): void
    {
        $newAvailable = $this->availableCopies + ($newTotal->value - $this->totalCopies);
        if ($newAvailable < 0) {
            throw new DomainValidationError('El nuevo total deja los ejemplares disponibles por debajo de cero.');
        }
        $this->totalCopies = $newTotal->value;
        $this->availableCopies = $newAvailable;
    }

    /**
     * Replaces title, author, category and total in one step (PUT /books/{id}); nothing changes if any value is rejected.
     */
    public function replaceDetails(string $title, string $author, string $categoryId, Quantity $total): void
    {
        $newTitle = self::requireText($title, 'El título es obligatorio.');
        $newAuthor = self::requireText($author, 'El autor es obligatorio.');
        $this->changeTotal($total);
        $this->title = $newTitle;
        $this->author = $newAuthor;
        $this->categoryId = $categoryId;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function setCover(string $key): void
    {
        if (trim($key) === '') {
            throw new DomainValidationError('La clave de la portada no puede estar vacía.');
        }
        $this->coverKey = $key;
    }

    public function removeCover(): void
    {
        $this->coverKey = null;
    }

    private static function requireText(string $value, string $message): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new DomainValidationError($message);
        }

        return $trimmed;
    }
}
