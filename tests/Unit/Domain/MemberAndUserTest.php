<?php

declare(strict_types=1);

use App\Domain\DocumentNumber;
use App\Domain\DomainValidationError;
use App\Domain\Member;
use App\Domain\Role;
use App\Domain\User;

it('creates a member with a trimmed name and a document', function () {
    $member = Member::create(' Luis Pérez ', new DocumentNumber('1020'));

    expect($member->fullName)->toBe('Luis Pérez')->and($member->document->value)->toBe('1020');
});

it('rejects a member without name', function () {
    Member::create('  ', new DocumentNumber('1020'));
})->throws(DomainValidationError::class);

it('normalizes the username to lower case and trimmed', function () {
    $user = User::newLibrarian('Ana ', 'hash', 'librarian');

    expect($user->username)->toBe('ana')->and($user->role)->toBe(Role::Librarian);
});

it('rejects the admin role through the librarian creation path', function () {
    User::newLibrarian('ana', 'hash', 'admin');
})->throws(DomainValidationError::class);

it('rejects an unknown role and an empty username', function () {
    expect(fn () => User::newLibrarian('ana', 'hash', 'root'))->toThrow(DomainValidationError::class)
        ->and(fn () => User::newLibrarian('  ', 'hash', 'librarian'))->toThrow(DomainValidationError::class);
});
