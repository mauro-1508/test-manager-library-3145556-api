<?php

declare(strict_types=1);

use App\Application\Dto\PageRequest;
use App\Application\Errors\DocumentTaken;
use App\Application\Errors\NotFound;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use Tests\Unit\Application\Fakes\Library;

it('creates a member with name and document (CA-05.1)', function () {
    $lib = new Library();

    $member = (new CreateMemberService($lib->members))->execute(' Ana Pérez ', ' 1020304050 ');

    expect($member->fullName)->toBe('Ana Pérez')
        ->and($member->document->value)->toBe('1020304050')
        ->and($lib->members->find($member->id))->not->toBeNull();
});

it('rejects a repeated document with DOCUMENT_TAKEN (CA-05.1)', function () {
    $lib = new Library();
    $service = new CreateMemberService($lib->members);
    $service->execute('Ana', '123');

    try {
        $service->execute('Otra Ana', '123');
        $this->fail('Expected DocumentTaken.');
    } catch (DocumentTaken $error) {
        expect($error->errorCode())->toBe('DOCUMENT_TAKEN');
    }
});

it('searches members by name or document, paginated (CA-05.2)', function () {
    $lib = new Library();
    $create = new CreateMemberService($lib->members);
    $create->execute('Ana Pérez', '111');
    $create->execute('Luis Gómez', '222');
    $create->execute('Anabel Ruiz', '333');
    $search = new SearchMembersService($lib->members);

    $byName = $search->execute('ana', new PageRequest());
    $byDocument = $search->execute('222', new PageRequest());
    $paged = $search->execute(null, new PageRequest(2, 2));

    expect($byName->total)->toBe(2)
        ->and($byDocument->items[0]->fullName)->toBe('Luis Gómez')
        ->and($paged->total)->toBe(3)
        ->and($paged->items)->toHaveCount(1);
});

it('gets a member with the count of unreturned loan lines', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $first = $lib->addBook('Uno');
    $second = $lib->addBook('Dos');
    $lib->lendService()->execute($member->id, [$first->id, $second->id], 'user-1');

    $view = (new GetMemberService($lib->members, $lib->loans))->execute($member->id);

    expect($view->activeLoans)->toBe(2)
        ->and($view->member->id)->toBe($member->id);
});

it('answers NOT_FOUND for an unknown member', function () {
    $lib = new Library();

    (new GetMemberService($lib->members, $lib->loans))->execute('missing');
})->throws(NotFound::class);
