<?php

declare(strict_types=1);

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgLoanReader;
use App\Adapters\Outbound\Persistence\PgLoanRepository;
use App\Adapters\Outbound\Persistence\PgReportReader;
use App\Application\Dto\LoanFilter;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Domain\DateRange;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\Quantity;
use Illuminate\Support\Facades\DB;
use Tests\Integration\Persistence\PersistenceTestCase;

uses(PersistenceTestCase::class)->group('integration');

function utc(string $value): DateTimeImmutable
{
    return new DateTimeImmutable($value, new DateTimeZone('UTC'));
}

/**
 * Lends the given books to a member at $at, through the repositories (as the service does).
 */
function lend(string $memberId, string $userId, array $bookIds, DateTimeImmutable $at): Loan
{
    $books = (new PgBookRepository())->findActiveByIds($bookIds);
    $loan = Loan::create($memberId, $userId, $at);
    foreach ($books as $book) {
        $loan->addItem($book, 'Novela');
    }
    (new PgLoanRepository())->save($loan, $books);

    return $loan;
}

it('saves a loan with its lines and takes one copy per book; find returns it with UTC instants', function () {
    $member = $this->newMember();
    $user = $this->newLibrarian();
    $book = $this->newBook('Dune', 2);

    $loan = lend($member->id, $user->id, [$book->id], utc('2026-03-01 08:30:00.123456'));

    $found = (new PgLoanRepository())->find($loan->id);
    expect($found->memberId)->toBe($member->id)
        ->and($found->loanedByUserId)->toBe($user->id)
        ->and($found->loanedAt->format('Y-m-d H:i:s.u'))->toBe('2026-03-01 08:30:00.123456')
        ->and($found->loanedAt->getTimezone()->getName())->toBe('UTC')
        ->and($found->items())->toHaveCount(1)
        ->and($found->items()[0]->bookTitle)->toBe('Dune')
        ->and($found->items()[0]->dueAt->format('Y-m-d H:i:s'))->toBe('2026-03-15 08:30:00')
        ->and($found->items()[0]->returnedAt)->toBeNull();
    expect((new PgBookRepository())->findActive($book->id)->availableCopies())->toBe(1);
    expect((new PgLoanRepository())->find(App\Domain\Uuid::v4()))->toBeNull();
});

it('returns a line: sets returned_at, restocks the book and a repeated return does not restock twice', function () {
    $member = $this->newMember();
    $user = $this->newLibrarian();
    $book = $this->newBook('Dune', 2);
    $loan = lend($member->id, $user->id, [$book->id], utc('2026-03-01 08:00:00'));

    $loans = new PgLoanRepository();
    $stored = $loans->find($loan->id);
    $readBook = (new PgBookRepository())->findActive($book->id);
    $stored->returnItem($stored->items()[0]->id, $readBook, utc('2026-03-02 09:00:00'));
    $loans->save($stored, [$readBook]);

    $after = $loans->find($loan->id);
    expect($after->items()[0]->returnedAt->format('Y-m-d H:i:s'))->toBe('2026-03-02 09:00:00')
        ->and((new PgBookRepository())->findActive($book->id)->availableCopies())->toBe(2);

    // A stale copy that returns the same line again changes nothing (CA-07.2).
    $staleBook = (new PgBookRepository())->findActive($book->id);
    $loans->save($loan, [$staleBook]);
    expect((new PgBookRepository())->findActive($book->id)->availableCopies())->toBe(2);
});

it('RNF-2: two lends of the last copy with the same read value: exactly one succeeds, nothing of the other is saved', function () {
    $books = new PgBookRepository();
    $loans = new PgLoanRepository();
    $user = $this->newLibrarian();
    $memberA = $this->newMember();
    $memberB = $this->newMember();
    $book = $this->newBook('Last copy', 1);

    // Both librarians read available_copies = 1 before either writes.
    $readA = $books->findActiveByIds([$book->id])[0];
    $readB = $books->findActiveByIds([$book->id])[0];
    $loanA = Loan::create($memberA->id, $user->id, utc('2026-03-01 10:00:00'));
    $loanA->addItem($readA, 'Novela');
    $loanB = Loan::create($memberB->id, $user->id, utc('2026-03-01 10:00:01'));
    $loanB->addItem($readB, 'Novela');

    $loans->save($loanA, [$readA]);
    expect(fn () => $loans->save($loanB, [$readB]))->toThrow(ConcurrencyConflict::class);

    expect($books->findActive($book->id)->availableCopies())->toBe(0)
        ->and($loans->find($loanA->id))->not->toBeNull()
        ->and($loans->find($loanB->id))->toBeNull()
        ->and(DB::table('library.loan_item')->where('book_id', $book->id)->count())->toBe(1);
});

it('maps a CHECK violation (ck_book_available_range) to ConcurrencyConflict', function () {
    $user = $this->newLibrarian();
    $member = $this->newMember();
    $book = $this->newBook('Edge', 2);
    $loan = lend($member->id, $user->id, [$book->id], utc('2026-03-01 10:00:00'));

    // Someone wrote outside the adapter: total is now 1, available still 1 (the value the reader saw).
    DB::table('library.book')->where('id', $book->id)->update(['total_copies' => 1]);
    $staleBook = new App\Domain\Book($book->id, 'Edge', 'Frank Herbert', $book->isbn, $book->categoryId(), 2, 1);
    $stored = (new PgLoanRepository())->find($loan->id);
    $stored->returnItem($stored->items()[0]->id, $staleBook, utc('2026-03-02 10:00:00'));

    expect(fn () => (new PgLoanRepository())->save($stored, [$staleBook]))->toThrow(ConcurrencyConflict::class);
    expect(DB::table('library.loan_item')->where('loan_id', $loan->id)->whereNull('returned_at')->count())->toBe(1);
});

it('computes status, filters by inclusive UTC days and by member, newest first', function () {
    $user = $this->newLibrarian();
    $member = $this->newMember();
    $other = $this->newMember();
    $b1 = $this->newBook('One', 1);
    $b2 = $this->newBook('Two', 1);
    $b3 = $this->newBook('Three', 1);
    $reader = new PgLoanReader();
    $now = utc('2026-03-20 12:00:00');

    $overdue = lend($member->id, $user->id, [$b1->id], utc('2026-03-01 23:59:59'));   // due 03-15: overdue
    $open = lend($member->id, $user->id, [$b2->id], utc('2026-03-10 00:00:00'));       // due 03-24: open
    $closed = lend($other->id, $user->id, [$b3->id], utc('2026-03-11 10:00:00'));
    $loans = new PgLoanRepository();
    $toReturn = $loans->find($closed->id);
    $book3 = (new PgBookRepository())->findActive($b3->id);
    $toReturn->returnItem($toReturn->items()[0]->id, $book3, utc('2026-03-12 10:00:00'));
    $loans->save($toReturn, [$book3]);

    $ids = fn (LoanFilter $f) => array_map(fn (Loan $l) => $l->id, $reader->search($f, new PageRequest(), $now)->items);

    expect($ids(new LoanFilter(status: LoanStatus::Overdue)))->toBe([$overdue->id])
        ->and($ids(new LoanFilter(status: LoanStatus::Open)))->toBe([$open->id])
        ->and($ids(new LoanFilter(status: LoanStatus::Closed)))->toBe([$closed->id])
        ->and($ids(new LoanFilter(memberId: $member->id)))->toBe([$open->id, $overdue->id]);

    // Both ends are inclusive calendar days: 03-01 23:59:59 is inside [03-01, 03-01]; 03-10 00:00:00 is inside [03-10, 03-10].
    expect($ids(new LoanFilter(utc('2026-03-01 15:00:00'), utc('2026-03-01 16:00:00'))))->toBe([$overdue->id])
        ->and($ids(new LoanFilter(utc('2026-03-10'), utc('2026-03-10'))))->toBe([$open->id])
        ->and($ids(new LoanFilter(utc('2026-03-02'), utc('2026-03-09'))))->toBe([]);

    $page = $reader->search(new LoanFilter(), new PageRequest(1, 2), $now);
    expect($page->total)->toBe(3)->and($page->items)->toHaveCount(2);
    expect($page->items[0]->items())->toHaveCount(1);
});

it('reads unreturned lines of a member, overdue candidates and open loans of a book', function () {
    $user = $this->newLibrarian();
    $member = $this->newMember();
    $b1 = $this->newBook('Late', 1);
    $b2 = $this->newBook('Fine', 1);
    $reader = new PgLoanReader();
    $now = utc('2026-03-20 12:00:00');

    $late = lend($member->id, $user->id, [$b1->id], utc('2026-03-01 08:00:00'));
    lend($member->id, $user->id, [$b2->id], utc('2026-03-19 08:00:00'));

    expect($reader->findUnreturnedItemsOfMember($member->id))->toHaveCount(2)
        ->and($reader->findUnreturnedItemsOfMember(App\Domain\Uuid::v4()))->toBe([]);

    $candidates = $reader->findOverdueCandidates($now);
    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->loanId)->toBe($late->id)
        ->and($candidates[0]->memberName)->toBe($member->fullName)
        ->and($candidates[0]->item->bookTitle)->toBe('Late');

    expect($reader->hasOpenLoansForBook($b1->id))->toBeTrue()
        ->and($reader->hasOpenLoansForBook($this->newBook('Never lent')->id))->toBeFalse();
});

it('ADR-004: the report keeps the frozen title after the book is renamed, counts and orders rows, ranges are inclusive', function () {
    $books = new PgBookRepository();
    $user = $this->newLibrarian();
    $m1 = $this->newMember();
    $m2 = $this->newMember();
    $popular = $this->newBook('Original Title', 2);
    $other = $this->newBook('Another', 2);

    lend($m1->id, $user->id, [$popular->id, $other->id], utc('2026-04-01 00:00:00'));
    lend($m2->id, $user->id, [$popular->id], utc('2026-04-30 23:59:59'));
    $outside = $this->newMember();
    lend($outside->id, $user->id, [$other->id], utc('2026-05-01 00:00:00'));

    $renamed = $books->findActive($popular->id);
    $renamed->replaceDetails('Renamed Title', 'Frank Herbert', PersistenceTestCase::SCIENCE, new Quantity(2));
    $books->save($renamed);

    $rows = (new PgReportReader())->mostBorrowed(new DateRange(utc('2026-04-01'), utc('2026-04-30')));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->bookId)->toBe($popular->id)
        ->and($rows[0]->bookTitle)->toBe('Original Title')
        ->and($rows[0]->categoryName)->toBe('Novela')
        ->and($rows[0]->timesBorrowed)->toBe(2)
        ->and($rows[1]->bookTitle)->toBe('Another')
        ->and($rows[1]->timesBorrowed)->toBe(1);
});
