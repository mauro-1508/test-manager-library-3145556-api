<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\DomainValidationError;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;

/**
 * Startup use case: provisions the initial admin from environment credentials (plan D-10).
 */
final class EnsureInitialAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /**
     * @return bool true when the admin was created, false when that username already existed
     */
    public function execute(string $username, string $password): bool
    {
        $normalized = User::normalizeUsername($username);
        if ($this->users->findByUsername($normalized) !== null) {
            return false;
        }
        if (trim($password) === '') {
            throw new DomainValidationError('La contraseña es obligatoria.');
        }
        $this->users->save(new User(Uuid::v4(), $normalized, $this->hasher->hash($password), Role::Admin));

        return true;
    }
}
