<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgLoanRepository implements LoanRepository
{
    private const CHECK_VIOLATION = '23514';

    public function save(Loan $loan, array $books): void
    {
        DB::transaction(function () use ($loan, $books): void {
            $stored = DB::table('library.loan_item')->where('loan_id', $loan->id)->pluck('returned_at', 'id')->all();
            if ($stored === [] && !DB::table('library.loan')->where('id', $loan->id)->exists()) {
                DB::table('library.loan')->insert([
                    'id' => $loan->id,
                    'member_id' => $loan->memberId,
                    'loaned_by_user_id' => $loan->loanedByUserId,
                    'loaned_at' => PgTime::toDb($loan->loanedAt),
                ]);
            }

            // Copies taken (-1) or given back (+1) by this save, per book: tells the guard which value was read.
            $delta = [];
            foreach ($loan->items() as $item) {
                if (!array_key_exists($item->id, $stored)) {
                    DB::table('library.loan_item')->insert([
                        'id' => $item->id,
                        'loan_id' => $loan->id,
                        'book_id' => $item->bookId,
                        'book_title' => $item->bookTitle,
                        'isbn' => $item->isbn,
                        'category_name' => $item->categoryName,
                        'due_at' => PgTime::toDb($item->dueAt),
                        'returned_at' => $item->returnedAt === null ? null : PgTime::toDb($item->returnedAt),
                    ]);
                    $delta[$item->bookId] = ($delta[$item->bookId] ?? 0) - 1 + ($item->isReturned() ? 1 : 0);
                } elseif ($stored[$item->id] === null && $item->isReturned()) {
                    $updated = DB::table('library.loan_item')
                        ->where('id', $item->id)->whereNull('returned_at')
                        ->update(['returned_at' => PgTime::toDb($item->returnedAt)]);
                    if ($updated === 0) {
                        throw new ConcurrencyConflict($item->bookId);
                    }
                    $delta[$item->bookId] = ($delta[$item->bookId] ?? 0) + 1;
                }
            }

            foreach ($books as $book) {
                $change = $delta[$book->id] ?? 0;
                if ($change !== 0) {
                    $this->conditionalUpdate($book->id, $book->availableCopies() - $change, $book->availableCopies());
                }
            }
        });
    }

    public function find(string $id): ?Loan
    {
        $row = DB::table('library.loan')->where('id', $id)->first();

        return $row === null ? null : LoanHydrator::loans([$row])[0];
    }

    /**
     * ADR-002: applies only if the row still holds the value that was read; the CHECK is the last barrier.
     */
    private function conditionalUpdate(string $bookId, int $readValue, int $newValue): void
    {
        try {
            $affected = DB::table('library.book')
                ->where('id', $bookId)
                ->where('available_copies', $readValue)
                ->update(['available_copies' => $newValue]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? $e->getCode()) === self::CHECK_VIOLATION) {
                throw new ConcurrencyConflict($bookId);
            }
            throw $e;
        }
        if ($affected === 0) {
            throw new ConcurrencyConflict($bookId);
        }
    }
}
