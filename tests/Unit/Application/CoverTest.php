<?php

declare(strict_types=1);

use App\Application\Errors\NotFound;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\UploadCoverService;
use App\Domain\DomainValidationError;
use Tests\Unit\Application\Fakes\Library;

function uploadCover(Library $lib): UploadCoverService
{
    return new UploadCoverService($lib->books, $lib->covers, $lib->assembler);
}

function removeCover(Library $lib): RemoveCoverService
{
    return new RemoveCoverService($lib->books, $lib->covers, $lib->assembler);
}

it('stores a PNG of exactly 2 MB and saves an opaque key (CA-03.4)', function () {
    $lib = new Library();
    $book = $lib->addBook();

    $view = uploadCover($lib)->execute($book->id, str_repeat('x', UploadCoverService::MAX_BYTES), 'image/png');

    expect($view->book->coverKey())->not->toBeNull()
        ->and($lib->books->stored($book->id)->coverKey())->toBe($view->book->coverKey())
        ->and($lib->covers->has($view->book->coverKey()))->toBeTrue();
});

it('accepts JPEG', function () {
    $lib = new Library();
    $book = $lib->addBook();

    expect(uploadCover($lib)->execute($book->id, 'bytes', 'image/jpeg')->book->coverKey())->not->toBeNull();
});

it('rejects a cover over 2 MB and stores nothing (CA-03.4)', function () {
    $lib = new Library();
    $book = $lib->addBook();

    expect(fn () => uploadCover($lib)->execute($book->id, str_repeat('x', UploadCoverService::MAX_BYTES + 1), 'image/png'))
        ->toThrow(DomainValidationError::class)
        ->and($lib->covers->count())->toBe(0)
        ->and($lib->books->stored($book->id)->coverKey())->toBeNull();
});

it('rejects types other than JPEG and PNG', function (string $mime) {
    $lib = new Library();
    $book = $lib->addBook();

    uploadCover($lib)->execute($book->id, 'bytes', $mime);
})->with(['image/gif', 'application/pdf', 'text/plain'])->throws(DomainValidationError::class);

it('answers NOT_FOUND when uploading to a missing book', function () {
    uploadCover(new Library())->execute('missing', 'bytes', 'image/png');
})->throws(NotFound::class);

it('replaces a previous cover and deletes the old binary', function () {
    $lib = new Library();
    $book = $lib->addBook();
    $first = uploadCover($lib)->execute($book->id, 'one', 'image/png')->book->coverKey();

    $second = uploadCover($lib)->execute($book->id, 'two', 'image/png')->book->coverKey();

    expect($second)->not->toBe($first)
        ->and($lib->covers->has($first))->toBeFalse()
        ->and($lib->covers->has($second))->toBeTrue();
});

it('removing a cover nulls the key and persists it before deleting the binary (data-model section 7)', function () {
    $lib = new Library();
    $book = $lib->addBook();
    $key = uploadCover($lib)->execute($book->id, 'one', 'image/png')->book->coverKey();
    $keyInDatabaseWhenBinaryIsDeleted = 'not called';
    $lib->covers->beforeDelete = function () use ($lib, $book, &$keyInDatabaseWhenBinaryIsDeleted): void {
        $keyInDatabaseWhenBinaryIsDeleted = $lib->books->stored($book->id)->coverKey();
    };

    $view = removeCover($lib)->execute($book->id);

    expect($keyInDatabaseWhenBinaryIsDeleted)->toBeNull()
        ->and($view->book->coverKey())->toBeNull()
        ->and($lib->covers->has($key))->toBeFalse();
});

it('removing a book without cover changes nothing and keeps null, never an empty string', function () {
    $lib = new Library();
    $book = $lib->addBook();

    expect(removeCover($lib)->execute($book->id)->book->coverKey())->toBeNull();
});
