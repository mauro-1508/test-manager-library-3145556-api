<?php

declare(strict_types=1);

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgCategoryRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Application\Dto\PageRequest;
use App\Domain\DocumentNumber;
use App\Domain\Isbn;
use App\Domain\Member;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;
use Tests\Integration\Persistence\PersistenceTestCase;

uses(PersistenceTestCase::class)->group('integration');

it('lists the five seeded categories ordered by name and finds one by id', function () {
    $categories = (new PgCategoryRepository())->all();

    expect(array_map(fn ($c) => $c->name, $categories))->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología']);
    expect((new PgCategoryRepository())->find(PersistenceTestCase::NOVEL)?->name)->toBe('Novela');
    expect((new PgCategoryRepository())->find(Uuid::v4()))->toBeNull();
});

it('round-trips a book, including cover and update', function () {
    $repo = new PgBookRepository();
    $book = $this->newBook('Dune', 3, '9780441013593');

    $found = $repo->findActive($book->id);
    expect($found->title())->toBe('Dune')
        ->and($found->isbn->value)->toBe('9780441013593')
        ->and($found->totalCopies())->toBe(3)
        ->and($found->availableCopies())->toBe(3)
        ->and($found->coverKey())->toBeNull();

    $found->setCover('covers/abc');
    $found->replaceDetails('Dune Messiah', 'Frank Herbert', PersistenceTestCase::SCIENCE, new App\Domain\Quantity(5));
    $repo->save($found);

    $again = $repo->findActive($book->id);
    expect($again->title())->toBe('Dune Messiah')
        ->and($again->categoryId())->toBe(PersistenceTestCase::SCIENCE)
        ->and($again->totalCopies())->toBe(5)
        ->and($again->availableCopies())->toBe(5)
        ->and($again->coverKey())->toBe('covers/abc');
});

it('hides soft-deleted books everywhere and lets the ISBN be registered again', function () {
    $repo = new PgBookRepository();
    $book = $this->newBook('Hidden', 1, '9780000000011');
    expect($repo->isIsbnTakenByActiveBook(new Isbn('9780000000011')))->toBeTrue();

    $book->softDelete(new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC')));
    $repo->save($book);

    expect($repo->findActive($book->id))->toBeNull()
        ->and($repo->findActiveByIds([$book->id]))->toBe([])
        ->and($repo->isIsbnTakenByActiveBook(new Isbn('9780000000011')))->toBeFalse()
        ->and($repo->search('Hidden', null, new PageRequest())->total)->toBe(0);

    $this->newBook('Hidden again', 1, '9780000000011');
    expect($repo->isIsbnTakenByActiveBook(new Isbn('9780000000011')))->toBeTrue();
});

it('finds books by a batch of ids', function () {
    $repo = new PgBookRepository();
    $a = $this->newBook('A');
    $b = $this->newBook('B');

    $found = $repo->findActiveByIds([$a->id, $b->id, Uuid::v4()]);

    expect($found)->toHaveCount(2);
    expect($repo->findActiveByIds([]))->toBe([]);
});

it('searches by title or author, filters by category, orders by title and paginates with total', function () {
    $repo = new PgBookRepository();
    $this->newBook('Zeta Quasar', 1, null, PersistenceTestCase::SCIENCE);
    $this->newBook('alpha quasar', 1, null, PersistenceTestCase::NOVEL);
    $this->newBook('Mid quasar', 1, null, PersistenceTestCase::NOVEL);
    $this->newBook('100% real_book', 1, null, PersistenceTestCase::NOVEL);

    $page = $repo->search('QUASAR', null, new PageRequest(1, 2));
    expect($page->total)->toBe(3)
        ->and(array_map(fn ($b) => $b->title(), $page->items))->toBe(['alpha quasar', 'Mid quasar']);

    $page2 = $repo->search('QUASAR', null, new PageRequest(2, 2));
    expect(array_map(fn ($b) => $b->title(), $page2->items))->toBe(['Zeta Quasar']);

    $byCategory = $repo->search('quasar', PersistenceTestCase::SCIENCE, new PageRequest());
    expect($byCategory->total)->toBe(1);

    $byAuthor = $repo->search('herbert', PersistenceTestCase::SCIENCE, new PageRequest());
    expect($byAuthor->total)->toBe(1);

    expect($repo->search('100%', null, new PageRequest())->total)->toBe(1)
        ->and($repo->search('%', null, new PageRequest())->total)->toBe(1);
});

it('round-trips members, detects taken documents and searches by name or document', function () {
    $repo = new PgMemberRepository();
    $member = Member::create('Zoe Zapata', new DocumentNumber('DOC-777'));
    $repo->save($member);
    $repo->save(Member::create('Abel Arias', new DocumentNumber('555')));

    expect($repo->find($member->id)?->fullName)->toBe('Zoe Zapata')
        ->and($repo->find($member->id)?->document->value)->toBe('DOC-777')
        ->and($repo->find(Uuid::v4()))->toBeNull()
        ->and($repo->isDocumentTaken(new DocumentNumber('DOC-777')))->toBeTrue()
        ->and($repo->isDocumentTaken(new DocumentNumber('nope')))->toBeFalse();

    $byName = $repo->search('zapata', new PageRequest());
    expect($byName->total)->toBe(1);
    expect($repo->search('doc-7', new PageRequest())->total)->toBe(1);

    $all = $repo->search(null, new PageRequest());
    expect($all->items[0]->fullName)->toBe('Abel Arias');
});

it('round-trips users and finds them by normalized username', function () {
    $repo = new PgUserRepository();
    $user = new User(Uuid::v4(), 'maria', 'argon-hash', Role::Admin);
    $repo->save($user);

    $found = $repo->findByUsername('maria');
    expect($found->id)->toBe($user->id)
        ->and($found->passwordHash)->toBe('argon-hash')
        ->and($found->role)->toBe(Role::Admin)
        ->and($repo->findByUsername('nobody'))->toBeNull();
});
