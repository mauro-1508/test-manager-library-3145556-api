<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $username,
        public string $passwordHash,
        public Role $role,
    ) {
    }

    /**
     * Creation path used by the API: only the librarian role is allowed (CA-02.3).
     */
    public static function newLibrarian(string $username, string $passwordHash, string $requestedRole): self
    {
        if (Role::tryFrom($requestedRole) !== Role::Librarian) {
            throw new DomainValidationError('Solo se puede crear un usuario con el rol de bibliotecario.');
        }

        return new self(Uuid::v4(), self::normalizeUsername($username), $passwordHash, Role::Librarian);
    }

    public static function normalizeUsername(string $username): string
    {
        $normalized = mb_strtolower(trim($username));
        if ($normalized === '') {
            throw new DomainValidationError('El nombre de usuario es obligatorio.');
        }

        return $normalized;
    }
}
