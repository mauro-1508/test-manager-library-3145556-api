<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Errors\UsernameTaken;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\DomainValidationError;
use App\Domain\User;

final class CreateLibrarianService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function execute(string $username, string $password, string $requestedRole): User
    {
        if (trim($password) === '') {
            throw new DomainValidationError('La contraseña es obligatoria.');
        }

        $user = User::newLibrarian($username, $this->hasher->hash($password), $requestedRole);
        if ($this->users->findByUsername($user->username) !== null) {
            throw new UsernameTaken();
        }
        $this->users->save($user);

        return $user;
    }
}
