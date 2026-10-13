<?php

namespace App\Bootstrap\Composition;

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgCategoryRepository;
use App\Adapters\Outbound\Persistence\PgLoanReader;
use App\Adapters\Outbound\Persistence\PgLoanRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgReportReader;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Security\JwtTokenVerifier;
use App\Adapters\Outbound\Storage\DiskCoverStorage;
use App\Adapters\Outbound\Time\SystemClock;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\ReportReader;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\TokenVerifier;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Single composition point: the only place where a port meets its adapter (constitution, Art. III).
 */
class PortBindings extends ServiceProvider
{
    private const COVERS_DISK = 'covers';

    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(PasswordHasher::class, Argon2idPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, fn (): TokenIssuer => new JwtTokenIssuer($this->jwtSecret()));
        $this->app->bind(TokenVerifier::class, fn (): TokenVerifier => new JwtTokenVerifier($this->jwtSecret()));
        $this->app->bind(CoverStorage::class, fn (): CoverStorage => new DiskCoverStorage(Storage::disk(self::COVERS_DISK)));

        $this->app->bind(BookRepository::class, PgBookRepository::class);
        $this->app->bind(CategoryRepository::class, PgCategoryRepository::class);
        $this->app->bind(MemberRepository::class, PgMemberRepository::class);
        $this->app->bind(UserRepository::class, PgUserRepository::class);
        $this->app->bind(LoanRepository::class, PgLoanRepository::class);
        $this->app->bind(LoanReader::class, PgLoanReader::class);
        $this->app->bind(ReportReader::class, PgReportReader::class);
    }

    private function jwtSecret(): string
    {
        $secret = (string) config('library.jwt_secret');
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }

        return $secret;
    }
}
