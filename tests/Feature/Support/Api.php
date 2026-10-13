<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\ReportReader;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;
use Tests\Unit\Application\Fakes\Library;

/**
 * Contract-test harness: the real HTTP adapter and the real security adapters on top of the in-memory persistence fakes.
 */
final class Api
{
    public const JWT_SECRET = 'contract-tests-secret-with-more-than-32-bytes';
    public const PASSWORD = 'S3cret-pass';

    private function __construct(public readonly Library $library)
    {
    }

    public static function boot(): self
    {
        config(['library.jwt_secret' => self::JWT_SECRET]);
        $library = new Library();
        $app = app();
        $app->instance(BookRepository::class, $library->books);
        $app->instance(CategoryRepository::class, $library->categories);
        $app->instance(MemberRepository::class, $library->members);
        $app->instance(UserRepository::class, $library->users);
        $app->instance(LoanRepository::class, $library->loans);
        $app->instance(LoanReader::class, $library->loans);
        $app->instance(ReportReader::class, $library->reports);
        $app->instance(Clock::class, $library->clock);
        $app->instance(CoverStorage::class, $library->covers);

        return new self($library);
    }

    public function addUser(Role $role, string $username): User
    {
        $hash = app(\App\Application\Ports\Outbound\PasswordHasher::class)->hash(self::PASSWORD);
        $user = new User(Uuid::v4(), $username, $hash, $role);
        $this->library->users->save($user);

        return $user;
    }

    /**
     * @return array<string, string> request headers carrying a valid token of a fresh user with that role
     */
    public function as(Role $role): array
    {
        return $this->bearer($this->addUser($role, $role->value . '-' . Uuid::v4()), 3600);
    }

    /**
     * @return array<string, string>
     */
    public function bearer(User $user, int $validForSeconds): array
    {
        return ['Authorization' => 'Bearer ' . app(TokenIssuer::class)->issue($user, $validForSeconds)];
    }
}
