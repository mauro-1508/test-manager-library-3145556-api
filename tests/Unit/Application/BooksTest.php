<?php

declare(strict_types=1);

use App\Application\Dto\PageRequest;
use App\Application\Errors\BookHasOpenLoans;
use App\Application\Errors\IsbnTaken;
use App\Application\Errors\NotFound;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\ListCategoriesService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Domain\DomainValidationError;
use Tests\Unit\Application\Fakes\InMemoryCategories;
use Tests\Unit\Application\Fakes\Library;

function createBook(Library $lib, string $isbn = '9780307474728', int $total = 4): App\Application\Dto\BookView
{
    return (new CreateBookService($lib->books, $lib->categories))
        ->execute('Cien años de soledad', 'Gabriel García Márquez', $isbn, InMemoryCategories::NOVEL_ID, $total);
}

function searchBooks(Library $lib): SearchBooksService
{
    return new SearchBooksService($lib->books, $lib->assembler);
}

// HU-03

it('creates a book with available copies equal to the total (CA-03.1)', function () {
    $lib = new Library();

    $view = createBook($lib, total: 4);

    expect($view->book->availableCopies())->toBe(4)
        ->and($view->book->coverKey())->toBeNull()
        ->and($view->category->name)->toBe('Novela')
        ->and($lib->books->findActive($view->book->id))->not->toBeNull();
});

it('rejects an ISBN that is not 13 digits (CA-03.2)', function () {
    createBook(new Library(), isbn: '123');
})->throws(DomainValidationError::class);

it('rejects an ISBN already used by an active book (CA-03.2)', function () {
    $lib = new Library();
    createBook($lib);

    createBook($lib);
})->throws(IsbnTaken::class);

it('allows reusing the ISBN of a deleted book', function () {
    $lib = new Library();
    $first = createBook($lib);
    (new DeleteBookService($lib->books, $lib->loans, $lib->clock))->execute($first->book->id);

    expect(createBook($lib)->book->id)->not->toBe($first->book->id);
});

it('rejects an unknown category and a total below one', function () {
    $lib = new Library();
    $service = new CreateBookService($lib->books, $lib->categories);

    expect(fn () => $service->execute('T', 'A', '9780307474728', 'missing', 1))->toThrow(DomainValidationError::class)
        ->and(fn () => $service->execute('T', 'A', '9780307474728', InMemoryCategories::NOVEL_ID, 0))
        ->toThrow(DomainValidationError::class);
});

it('replaces title, author, category and total, shifting available copies by the same difference (CA-03.3)', function () {
    $lib = new Library();
    $book = $lib->addBook(total: 3);
    $stored = $lib->books->findActive($book->id);
    $stored->withdraw();
    $lib->books->save($stored);

    $view = (new UpdateBookService($lib->books, $lib->categories))
        ->execute($book->id, 'Otro título', 'Otro autor', InMemoryCategories::SCIENCE_ID, 5);

    expect($view->book->totalCopies())->toBe(5)
        ->and($view->book->availableCopies())->toBe(4)
        ->and($view->category->name)->toBe('Ciencia')
        ->and($lib->books->stored($book->id)->title())->toBe('Otro título');
});

it('never leaves available copies below zero when lowering the total (CA-03.3)', function () {
    $lib = new Library();
    $book = $lib->addBook(total: 3);
    $stored = $lib->books->findActive($book->id);
    $stored->withdraw();
    $stored->withdraw();
    $lib->books->save($stored);

    $update = fn () => (new UpdateBookService($lib->books, $lib->categories))
        ->execute($book->id, 'Cien años de soledad', 'Autor', InMemoryCategories::NOVEL_ID, 1);

    expect($update)->toThrow(DomainValidationError::class)
        ->and($lib->books->stored($book->id)->totalCopies())->toBe(3);
});

it('updating a missing book is NOT_FOUND', function () {
    $lib = new Library();

    (new UpdateBookService($lib->books, $lib->categories))
        ->execute('missing', 'T', 'A', InMemoryCategories::NOVEL_ID, 1);
})->throws(NotFound::class);

it('soft deletes a book without open loans, hiding it from the catalogue (CA-03.5)', function () {
    $lib = new Library();
    $book = $lib->addBook();

    (new DeleteBookService($lib->books, $lib->loans, $lib->clock))->execute($book->id);

    expect($lib->books->stored($book->id)->isDeleted())->toBeTrue()
        ->and(fn () => (new GetBookService($lib->books, $lib->assembler))->execute($book->id))
        ->toThrow(NotFound::class);
});

it('refuses to delete a book with open loans (CA-03.5)', function () {
    $lib = new Library();
    $book = $lib->addBook();
    $member = $lib->addMember();
    $lib->lendService()->execute($member->id, [$book->id], 'user-1');

    try {
        (new DeleteBookService($lib->books, $lib->loans, $lib->clock))->execute($book->id);
        $this->fail('Expected BookHasOpenLoans.');
    } catch (BookHasOpenLoans $error) {
        expect($error->errorCode())->toBe('BOOK_HAS_OPEN_LOANS')
            ->and($lib->books->stored($book->id)->isDeleted())->toBeFalse();
    }
});

// HU-04

it('searches by partial title or author, case insensitive, only active, ordered by title (CA-04.1)', function () {
    $lib = new Library();
    $lib->addBook('Rayuela', author: 'Julio Cortázar');
    $lib->addBook('Cien años de soledad', author: 'García Márquez');
    $lib->addBook('El Aleph', author: 'Borges');
    $deleted = $lib->addBook('Cien sonetos', author: 'Neruda');
    (new DeleteBookService($lib->books, $lib->loans, $lib->clock))->execute($deleted->id);

    $byTitle = searchBooks($lib)->execute('CIEN', null, new PageRequest());
    $byAuthor = searchBooks($lib)->execute('cortázar', null, new PageRequest());
    $all = searchBooks($lib)->execute(null, null, new PageRequest());

    expect(array_map(fn ($v) => $v->book->title(), $byTitle->items))->toBe(['Cien años de soledad'])
        ->and(array_map(fn ($v) => $v->book->title(), $byAuthor->items))->toBe(['Rayuela'])
        ->and(array_map(fn ($v) => $v->book->title(), $all->items))->toBe(['Cien años de soledad', 'El Aleph', 'Rayuela']);
});

it('filters by category and includes the category in each result (CA-04.1)', function () {
    $lib = new Library();
    $lib->addBook('Novela uno', categoryId: InMemoryCategories::NOVEL_ID);
    $lib->addBook('Física', categoryId: InMemoryCategories::SCIENCE_ID);

    $page = searchBooks($lib)->execute(null, InMemoryCategories::SCIENCE_ID, new PageRequest());

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->category->name)->toBe('Ciencia');
});

it('paginates and reports the total of results (CA-04.1, CA-04.2)', function () {
    $lib = new Library();
    foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
        $lib->addBook($title);
    }

    $page = searchBooks($lib)->execute(null, null, new PageRequest(2, 2));

    expect($page->total)->toBe(5)
        ->and($page->page)->toBe(2)
        ->and($page->size)->toBe(2)
        ->and(array_map(fn ($v) => $v->book->title(), $page->items))->toBe(['C', 'D']);
});

it('rejects a page size above 50', function () {
    new PageRequest(1, 51);
})->throws(DomainValidationError::class);

it('gets a book by id and answers NOT_FOUND for unknown or deleted ones (CA-04.3)', function () {
    $lib = new Library();
    $book = $lib->addBook();
    $get = new GetBookService($lib->books, $lib->assembler);

    expect($get->execute($book->id)->book->id)->toBe($book->id)
        ->and(fn () => $get->execute('missing'))->toThrow(NotFound::class);
});

it('lists the five categories ordered by name', function () {
    $lib = new Library();

    $names = array_map(fn ($c) => $c->name, (new ListCategoriesService($lib->categories))->execute());

    expect($names)->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología']);
});
