<?php

use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Security\JwtTokenVerifier;
use App\Domain\Role;
use App\Domain\User;

const UNIT_SECRET = 'unit-test-secret-with-more-than-32-bytes';

it('hashes with Argon2id, never returns the plain text and verifies', function (): void {
    $hasher = new Argon2idPasswordHasher();

    $hash = $hasher->hash('S3cret-pass');

    expect($hash)->toStartWith('$argon2id$')->not->toContain('S3cret-pass')
        ->and($hasher->verify('S3cret-pass', $hash))->toBeTrue()
        ->and($hasher->verify('other', $hash))->toBeFalse()
        ->and($hasher->hash('S3cret-pass'))->not->toBe($hash);
});

it('issues a token that the verifier reads back as user id and role', function (): void {
    $user = new User('9d6f0f6e-6a4d-4c43-9b63-0f7a3f1d2c11', 'ana', 'hash', Role::Librarian);

    $claims = (new JwtTokenVerifier(UNIT_SECRET))->verify((new JwtTokenIssuer(UNIT_SECRET))->issue($user, 60));

    expect($claims->userId)->toBe($user->id)->and($claims->role)->toBe(Role::Librarian);
});

it('refuses expired, tampered, foreign-signed and malformed tokens', function (): void {
    $user = new User('9d6f0f6e-6a4d-4c43-9b63-0f7a3f1d2c11', 'ana', 'hash', Role::Admin);
    $issuer = new JwtTokenIssuer(UNIT_SECRET);
    $verifier = new JwtTokenVerifier(UNIT_SECRET);
    $valid = $issuer->issue($user, 60);

    expect($verifier->verify($issuer->issue($user, -5)))->toBeNull()
        ->and($verifier->verify($valid . 'x'))->toBeNull()
        ->and($verifier->verify((new JwtTokenIssuer(str_repeat('z', 40)))->issue($user, 60)))->toBeNull()
        ->and($verifier->verify('not-a-jwt'))->toBeNull();
});
