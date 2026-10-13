<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Member
{
    public function __construct(
        public string $id,
        public string $fullName,
        public DocumentNumber $document,
    ) {
    }

    public static function create(string $fullName, DocumentNumber $document): self
    {
        $name = trim($fullName);
        if ($name === '') {
            throw new DomainValidationError('El nombre del socio es obligatorio.');
        }

        return new self(Uuid::v4(), $name, $document);
    }
}
