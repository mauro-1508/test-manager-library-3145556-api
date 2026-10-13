<?php

declare(strict_types=1);

use App\Application\Errors\InvalidCredentials;
use App\Application\Errors\UsernameTaken;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\EnsureInitialAdminService;
use App\Application\Services\LoginService;
use App\Domain\DomainValidationError;
use App\Domain\Role;
use Tests\Unit\Application\Fakes\Library;

function librarianCreator(Library $lib): CreateLibrarianService
{
    return new CreateLibrarianService($lib->users, $lib->hasher);
}

function loginService(Library $lib): LoginService
{
    return new LoginService($lib->users, $lib->hasher, $lib->tokens);
}

// HU-02

it('creates a librarian with the username lowercased and trimmed and the password hashed (CA-02.1, CA-02.2)', function () {
    $lib = new Library();

    $user = librarianCreator($lib)->execute('Ana ', 'secret-1', 'librarian');

    expect($user->username)->toBe('ana')
        ->and($user->role)->toBe(Role::Librarian)
        ->and($user->passwordHash)->not->toBe('secret-1')
        ->and($lib->users->findByUsername('ana'))->not->toBeNull();
});

it('rejects a repeated username even with different case (CA-02.2)', function () {
    $lib = new Library();
    librarianCreator($lib)->execute('ana', 'secret-1', 'librarian');

    librarianCreator($lib)->execute(' ANA', 'secret-2', 'librarian');
})->throws(UsernameTaken::class);

it('reports USERNAME_TAKEN with its business code', function () {
    $lib = new Library();
    librarianCreator($lib)->execute('ana', 'secret-1', 'librarian');

    try {
        librarianCreator($lib)->execute('ana', 'secret-2', 'librarian');
    } catch (UsernameTaken $error) {
        expect($error->errorCode())->toBe('USERNAME_TAKEN');
    }
});

it('rejects the admin role requested through the API with VALIDATION_ERROR (CA-02.3)', function () {
    $lib = new Library();

    try {
        librarianCreator($lib)->execute('boss', 'secret-1', 'admin');
        $this->fail('Expected a validation error.');
    } catch (DomainValidationError $error) {
        expect($error->errorCode())->toBe('VALIDATION_ERROR')
            ->and($lib->users->count())->toBe(0);
    }
});

// HU-01

it('logs in with valid credentials and returns an 8 hour token with the role (CA-01.1)', function () {
    $lib = new Library();
    librarianCreator($lib)->execute('ana', 'secret-1', 'librarian');

    $token = loginService($lib)->execute(' Ana', 'secret-1');

    expect($token->expiresIn)->toBe(28800)
        ->and($token->role)->toBe(Role::Librarian)
        ->and($token->accessToken)->not->toBe('')
        ->and($lib->tokens->lastValidity)->toBe(28800);
});

it('answers the same error whether the user or the password is wrong (CA-01.2)', function () {
    $lib = new Library();
    librarianCreator($lib)->execute('ana', 'secret-1', 'librarian');

    $messages = [];
    foreach ([['ghost', 'secret-1'], ['ana', 'wrong']] as [$username, $password]) {
        try {
            loginService($lib)->execute($username, $password);
            $this->fail('Expected invalid credentials.');
        } catch (InvalidCredentials $error) {
            $messages[] = [$error->errorCode(), $error->getMessage()];
        }
    }

    expect($messages[0])->toBe($messages[1])
        ->and($messages[0][0])->toBe('INVALID_CREDENTIALS');
});

// D-10

it('creates the initial admin once and can log in with it', function () {
    $lib = new Library();
    $service = new EnsureInitialAdminService($lib->users, $lib->hasher);

    expect($service->execute('Admin', 'admin-secret'))->toBeTrue()
        ->and($service->execute('admin', 'another-secret'))->toBeFalse()
        ->and($lib->users->count())->toBe(1)
        ->and(loginService($lib)->execute('admin', 'admin-secret')->role)->toBe(Role::Admin);
});
