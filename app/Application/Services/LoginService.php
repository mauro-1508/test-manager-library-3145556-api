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

    /** Argon2id hash of a throwaway value, verified when the user does not exist to equalise timing. */
    private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$V3NNNWF0OXFUcGFnV21SQw$LijAc5f6zY993/4oZz4cAZ+D5DS5rmFbge4a/bTG40c';

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function execute(string $username, string $password): AuthToken
    {
        $user = $this->users->findByUsername(User::normalizeUsername($username));
        $hash = $user === null ? self::DUMMY_PASSWORD_HASH : $user->passwordHash;
        $matches = $this->hasher->verify($password, $hash);
        if ($user === null || !$matches) {
            throw new InvalidCredentials();
        }

        return new AuthToken(
            $this->tokens->issue($user, self::TOKEN_VALIDITY_SECONDS),
            self::TOKEN_VALIDITY_SECONDS,
            $user->role,
        );
    }
}
