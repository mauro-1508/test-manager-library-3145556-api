<?php

declare(strict_types=1);

use App\Application\Dto\LoanFilter;
use App\Application\Dto\PageRequest;
use App\Application\Errors\NotFound;
use App\Application\Services\GetLoanService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\InvalidDateRange;
use App\Domain\ItemAlreadyReturned;
use App\Domain\LoanStatus;
use Tests\Unit\Application\Fakes\Library;

function returnService(Library $lib): ReturnItemService
{
    return new ReturnItemService($lib->loans, $lib->books, $lib->clock);
}

// HU-07

it('returns one line, sets returned_at and adds one available copy (CA-07.1)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook('Uno', 2);
    $loan = $lib->lendService()->execute($member->id, [$book->id], 'user-1')->loan;
    $lib->clock->set('2026-10-20T09:00:00Z');

    $item = returnService($lib)->execute($loan->id, $loan->items()[0]->id);

    expect($item->returnedAt?->format('Y-m-d'))->toBe('2026-10-20')
        ->and($lib->books->stored($book->id)->availableCopies())->toBe(2)
        ->and($lib->loans->find($loan->id)->items()[0]->isReturned())->toBeTrue();
});

it('returning the same line twice fails with ITEM_ALREADY_RETURNED and never adds a second copy (CA-07.2)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook('Uno', 3);
    $loan = $lib->lendService()->execute($member->id, [$book->id], 'user-1')->loan;
    $itemId = $loan->items()[0]->id;
    returnService($lib)->execute($loan->id, $itemId);

    try {
        returnService($lib)->execute($loan->id, $itemId);
        $this->fail('Expected ItemAlreadyReturned.');
    } catch (ItemAlreadyReturned $error) {
        expect($error->errorCode())->toBe('ITEM_ALREADY_RETURNED')
            ->and($lib->books->stored($book->id)->availableCopies())->toBe(3);
    }
});

it('closes the loan only when every line is returned (CA-07.4)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $loan = $lib->lendService()->execute($member->id, [$lib->addBook('A')->id, $lib->addBook('B')->id], 'user-1')->loan;
    $get = new GetLoanService($lib->loans, $lib->clock);

    returnService($lib)->execute($loan->id, $loan->items()[0]->id);
    $afterFirst = $get->execute($loan->id)->status;
    returnService($lib)->execute($loan->id, $loan->items()[1]->id);

    expect($afterFirst)->toBe(LoanStatus::Open)
        ->and($get->execute($loan->id)->status)->toBe(LoanStatus::Closed);
});

it('answers NOT_FOUND for an unknown loan or line', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $loan = $lib->lendService()->execute($member->id, [$lib->addBook()->id], 'user-1')->loan;

    expect(fn () => returnService($lib)->execute('missing', 'x'))->toThrow(NotFound::class)
        ->and(fn () => returnService($lib)->execute($loan->id, 'missing'))->toThrow(NotFound::class);
});

// HU-09

it('lists unreturned overdue lines, most days late first, with book title, member name and days late (CA-09.1, CA-09.2)', function () {
    $lib = new Library('2026-10-01T10:00:00Z');
    $ana = $lib->addMember('Ana Pérez');
    $luis = $lib->addMember('Luis Gómez');
    $lib->lendService()->execute($ana->id, [$lib->addBook('Reciente')->id], 'user-1');
    $lib->clock->set('2026-09-01T10:00:00Z');
    $lib->lendService()->execute($luis->id, [$lib->addBook('Antiguo')->id], 'user-1');
    $lib->clock->set('2026-10-20T10:00:00Z');

    $lines = (new OverdueService($lib->loans, $lib->clock))->execute();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->bookTitle)->toBe('Antiguo')
        ->and($lines[0]->memberName)->toBe('Luis Gómez')
        ->and($lines[0]->daysLate)->toBe(35)
        ->and($lines[0]->dueAt->format('Y-m-d'))->toBe('2026-09-15')
        ->and($lines[1]->bookTitle)->toBe('Reciente')
        ->and($lines[1]->daysLate)->toBe(5);
});

it('does not list returned lines nor lines that are not due yet', function () {
    $lib = new Library('2026-10-01T10:00:00Z');
    $member = $lib->addMember();
    $loan = $lib->lendService()->execute($member->id, [$lib->addBook('Devuelto')->id], 'user-1')->loan;
    $lib->clock->set('2026-10-05T10:00:00Z');
    $lib->lendService()->execute($lib->addMember('Otra')->id, [$lib->addBook('Vigente')->id], 'user-1');
    returnService($lib)->execute($loan->id, $loan->items()[0]->id);
    $lib->clock->set('2026-10-16T10:00:00Z');

    expect((new OverdueService($lib->loans, $lib->clock))->execute())->toBe([]);
});

// HU-08

it('gets a loan with its lines and a computed overdue status (CA-08.1, CA-08.3)', function () {
    $lib = new Library('2026-10-01T10:00:00Z');
    $loan = $lib->lendService()->execute($lib->addMember()->id, [$lib->addBook()->id], 'user-1')->loan;
    $get = new GetLoanService($lib->loans, $lib->clock);
    $statusWhileOpen = $get->execute($loan->id)->status;
    $lib->clock->set('2026-10-30T10:00:00Z');

    $view = $get->execute($loan->id);

    expect($statusWhileOpen)->toBe(LoanStatus::Open)
        ->and($view->status)->toBe(LoanStatus::Overdue)
        ->and($view->loan->items())->toHaveCount(1)
        ->and(fn () => $get->execute('missing'))->toThrow(NotFound::class);
});

it('lists loans newest first, filtered by range, status and member, paginated (CA-08.2)', function () {
    $lib = new Library('2026-10-01T10:00:00Z');
    $ana = $lib->addMember('Ana');
    $luis = $lib->addMember('Luis');
    $old = $lib->lendService()->execute($ana->id, [$lib->addBook('A')->id], 'user-1')->loan;
    $lib->clock->set('2026-10-10T10:00:00Z');
    $middle = $lib->lendService()->execute($luis->id, [$lib->addBook('B')->id], 'user-1')->loan;
    $lib->clock->set('2026-10-12T10:00:00Z');
    $recent = $lib->lendService()->execute($ana->id, [$lib->addBook('C')->id], 'user-1')->loan;
    returnService($lib)->execute($recent->id, $recent->items()[0]->id);
    $lib->clock->set('2026-10-30T10:00:00Z');
    $list = new ListLoansService($lib->loans, $lib->clock);
    $ids = fn ($page) => array_map(fn ($view) => $view->loan->id, $page->items);
    $day = fn (string $date) => new DateTimeImmutable($date, new DateTimeZone('UTC'));

    $all = $list->execute(new LoanFilter(), new PageRequest());
    $ranged = $list->execute(new LoanFilter(from: $day('2026-10-10'), to: $day('2026-10-10')), new PageRequest());
    $overdue = $list->execute(new LoanFilter(status: LoanStatus::Overdue), new PageRequest());
    $closed = $list->execute(new LoanFilter(status: LoanStatus::Closed), new PageRequest());
    $ofAna = $list->execute(new LoanFilter(memberId: $ana->id), new PageRequest());
    $paged = $list->execute(new LoanFilter(), new PageRequest(2, 2));

    expect($ids($all))->toBe([$recent->id, $middle->id, $old->id])
        ->and($all->total)->toBe(3)
        ->and($ids($ranged))->toBe([$middle->id])
        ->and($ids($overdue))->toBe([$middle->id, $old->id])
        ->and($ids($closed))->toBe([$recent->id])
        ->and($ids($ofAna))->toBe([$recent->id, $old->id])
        ->and($ids($paged))->toBe([$old->id])
        ->and($paged->total)->toBe(3)
        ->and($overdue->items[0]->status)->toBe(LoanStatus::Overdue);
});

it('rejects a loan filter whose end is before its start', function () {
    new LoanFilter(from: new DateTimeImmutable('2026-10-10'), to: new DateTimeImmutable('2026-10-01'));
})->throws(InvalidDateRange::class);
