<?php

declare(strict_types=1);

use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Services\ReturnItemService;
use App\Domain\ItemAlreadyReturned;
use App\Domain\Loan;
use Tests\Unit\Application\Fakes\Library;

// HU-07 · CA-07.2

it('translates a concurrency conflict on a return into ITEM_ALREADY_RETURNED (CA-07.2)', function () {
    $lib = new Library();
    $book = $lib->addBook('Uno', 2);
    $loan = $lib->lendService()->execute($lib->addMember()->id, [$book->id], 'user-1')->loan;
    $conflicting = new class ($lib->loans) implements LoanRepository {
        public function __construct(private readonly LoanRepository $inner)
        {
        }

        public function save(Loan $loan, array $books): void
        {
            throw new ConcurrencyConflict($books[0]->id);
        }

        public function find(string $id): ?Loan
        {
            return $this->inner->find($id);
        }
    };
    $service = new ReturnItemService($conflicting, $lib->books, $lib->clock);

    expect(fn () => $service->execute($loan->id, $loan->items()[0]->id))->toThrow(ItemAlreadyReturned::class);
});
