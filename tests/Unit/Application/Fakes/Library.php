<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Services\LendBooksService;
use App\Application\Support\BookViewAssembler;
use App\Domain\Book;
use App\Domain\DocumentNumber;
use App\Domain\Isbn;
use App\Domain\LendingPolicy;
use App\Domain\Member;
use App\Domain\Quantity;

/**
 * Bundle of in-memory fakes plus seeding helpers, shared by the application tests.
 */
final class Library
{
    private const BASE_ISBN = 9780307474728;

    public readonly InMemoryUsers $users;
    public readonly InMemoryCategories $categories;
    public readonly InMemoryBooks $books;
    public readonly InMemoryMembers $members;
    public readonly InMemoryLoans $loans;
    public readonly InMemoryReportReader $reports;
    public readonly InMemoryCoverStorage $covers;
    public readonly FakePasswordHasher $hasher;
    public readonly FakeTokenIssuer $tokens;
    public readonly FixedClock $clock;
    public readonly BookViewAssembler $assembler;

    private int $sequence = 0;

    public function __construct(string $now = '2026-10-14T14:15:00Z')
    {
        $this->users = new InMemoryUsers();
        $this->categories = new InMemoryCategories();
        $this->books = new InMemoryBooks();
        $this->members = new InMemoryMembers();
        $this->loans = new InMemoryLoans($this->books, $this->members);
        $this->reports = new InMemoryReportReader($this->loans);
        $this->covers = new InMemoryCoverStorage();
        $this->hasher = new FakePasswordHasher();
        $this->tokens = new FakeTokenIssuer();
        $this->clock = new FixedClock($now);
        $this->assembler = new BookViewAssembler($this->categories);
    }

    public function addBook(
        string $title = 'Cien años de soledad',
        int $total = 2,
        string $categoryId = InMemoryCategories::NOVEL_ID,
        string $author = 'Gabriel García Márquez',
    ): Book {
        $isbn = new Isbn((string) (self::BASE_ISBN + $this->sequence++));
        $book = Book::create($title, $author, $isbn, $categoryId, new Quantity($total));
        $this->books->save($book);

        return $book;
    }

    public function addMember(string $fullName = 'Ana Pérez'): Member
    {
        $member = Member::create($fullName, new DocumentNumber('DOC-' . $this->sequence++));
        $this->members->save($member);

        return $member;
    }

    public function lendService(): LendBooksService
    {
        return new LendBooksService(
            $this->members,
            $this->books,
            $this->categories,
            $this->loans,
            $this->loans,
            new LendingPolicy(),
            $this->clock,
        );
    }
}
