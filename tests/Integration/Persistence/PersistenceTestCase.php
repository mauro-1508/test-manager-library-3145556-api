<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Domain\Book;
use App\Domain\DocumentNumber;
use App\Domain\Isbn;
use App\Domain\Member;
use App\Domain\Quantity;
use App\Domain\Role;
use App\Domain\Uuid;
use App\Domain\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Runs each test inside a transaction that is rolled back, against the real PostgreSQL schema.
 * Skipped when no PostgreSQL connection is available.
 */
abstract class PersistenceTestCase extends TestCase
{
    public const NOVEL = '11111111-1111-4111-8111-111111111111';
    public const SCIENCE = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->databaseAvailable()) {
            $this->markTestSkipped('No PostgreSQL connection (set DATABASE_URL and run the migrations).');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    protected function newBook(string $title = 'Dune', int $copies = 1, ?string $isbn = null, string $categoryId = self::NOVEL): Book
    {
        $book = Book::create($title, 'Frank Herbert', new Isbn($isbn ?? $this->randomIsbn()), $categoryId, new Quantity($copies));
        (new PgBookRepository())->save($book);

        return $book;
    }

    protected function newMember(): Member
    {
        $member = Member::create('Ana Pérez', new DocumentNumber((string) random_int(100000, 999999999)));
        (new PgMemberRepository())->save($member);

        return $member;
    }

    protected function newLibrarian(): User
    {
        $user = new User(Uuid::v4(), 'lib' . random_int(1, 999999999), 'hash', Role::Librarian);
        (new PgUserRepository())->save($user);

        return $user;
    }

    protected function randomIsbn(): string
    {
        return str_pad((string) random_int(0, 9999999999999), 13, '0', STR_PAD_LEFT);
    }

    private function databaseAvailable(): bool
    {
        if (config('database.default') !== 'pgsql' || (string) env('DATABASE_URL') === '') {
            return false;
        }
        try {
            DB::select('SELECT 1 FROM library.book LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
