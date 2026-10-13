<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\AuthToken;
use App\Application\Errors\InvalidCredentials;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User;

final class LoginService
{
    public const TOKEN_VALIDITY_SECONDS = 8 * 3600;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function execute(string $username, string $password): AuthToken
    {
        $user = $this->users->findByUsername(User::normalizeUsername($username));
        if ($user === null || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials();
        }

        return new AuthToken(
            $this->tokens->issue($user, self::TOKEN_VALIDITY_SECONDS),
            self::TOKEN_VALIDITY_SECONDS,
            $user->role,
        );
    }
}
