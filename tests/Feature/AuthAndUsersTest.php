<?php

use App\Domain\Role;
use Tests\Feature\Support\Api;

beforeEach(function (): void {
    $this->api = Api::boot();
});

it('logs in and answers with a bearer token that lasts 8 hours', function (): void {
    $this->api->addUser(Role::Librarian, 'ana');

    $response = $this->postJson('/api/v1/auth/login', ['username' => ' Ana ', 'password' => Api::PASSWORD]);

    $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'role']);
    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('expires_in'))->toBe(28800)
        ->and($response->json('role'))->toBe('librarian');
});

it('puts only sub and role (plus exp) inside the token', function (): void {
    $user = $this->api->addUser(Role::Admin, 'root');
    $token = $this->postJson('/api/v1/auth/login', ['username' => 'root', 'password' => Api::PASSWORD])->json('access_token');

    $claims = (array) Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key(Api::JWT_SECRET, 'HS256'));

    expect(array_keys($claims))->toEqualCanonicalizing(['sub', 'role', 'exp'])
        ->and($claims['sub'])->toBe($user->id)
        ->and($claims['role'])->toBe('admin');
});

it('answers INVALID_CREDENTIALS 401 for a wrong password and for an unknown user alike', function (): void {
    $this->api->addUser(Role::Librarian, 'ana');

    foreach ([['ana', 'wrong'], ['ghost', Api::PASSWORD]] as [$username, $password]) {
        $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('details', []);
    }
});

it('rejects a login without fields with VALIDATION_ERROR and details', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('details.0.field', 'username')
        ->assertJsonPath('details.1.field', 'password');
});

it('never returns the password or its hash on login or on user creation (RNF-3)', function (): void {
    $this->api->addUser(Role::Admin, 'root');
    $login = $this->postJson('/api/v1/auth/login', ['username' => 'root', 'password' => Api::PASSWORD]);
    $created = $this->postJson(
        '/api/v1/users',
        ['username' => 'Ana ', 'password' => 'another-secret', 'role' => 'librarian'],
        ['Authorization' => 'Bearer ' . $login->json('access_token')],
    );

    $created->assertCreated()->assertExactJson([
        'id' => $created->json('id'),
        'username' => 'ana',
        'role' => 'librarian',
    ]);
    foreach ([$login, $created] as $response) {
        expect($response->getContent())->not->toContain('password')
            ->and($response->getContent())->not->toContain('argon2')
            ->and($response->getContent())->not->toContain('another-secret');
    }
});

it('stores the password hashed with Argon2id', function (): void {
    $this->postJson(
        '/api/v1/users',
        ['username' => 'ana', 'password' => 'another-secret', 'role' => 'librarian'],
        $this->api->as(Role::Admin),
    )->assertCreated();

    expect($this->api->library->users->findByUsername('ana')->passwordHash)->toStartWith('$argon2id$');
});

it('rejects creating a user with the admin role as VALIDATION_ERROR', function (): void {
    $this->postJson(
        '/api/v1/users',
        ['username' => 'eve', 'password' => 'x', 'role' => 'admin'],
        $this->api->as(Role::Admin),
    )->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('rejects a repeated username with USERNAME_TAKEN 409', function (): void {
    $headers = $this->api->as(Role::Admin);
    $body = ['username' => 'ana', 'password' => 'x', 'role' => 'librarian'];
    $this->postJson('/api/v1/users', $body, $headers)->assertCreated();

    $this->postJson('/api/v1/users', ['username' => 'ANA'] + $body, $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 'USERNAME_TAKEN');
});

it('forbids a librarian from creating users with FORBIDDEN_ROLE 403', function (): void {
    $this->postJson(
        '/api/v1/users',
        ['username' => 'ana', 'password' => 'x', 'role' => 'librarian'],
        $this->api->as(Role::Librarian),
    )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_ROLE')->assertJsonPath('details', []);
});

it('answers 401 without a token, with garbage, with a foreign signature and with an expired token', function (): void {
    $user = $this->api->addUser(Role::Admin, 'root');
    $forged = Firebase\JWT\JWT::encode(['sub' => $user->id, 'role' => 'admin', 'exp' => time() + 60], str_repeat('x', 40), 'HS256');

    $this->getJson('/api/v1/categories')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    $this->getJson('/api/v1/categories', ['Authorization' => 'Bearer garbage'])->assertStatus(401);
    $this->getJson('/api/v1/categories', ['Authorization' => 'Bearer ' . $forged])->assertStatus(401);
    $this->getJson('/api/v1/categories', $this->api->bearer($user, -10))->assertStatus(401);
});

it('does not require a token for health and login', function (): void {
    $this->getJson('/api/v1/health')->assertOk();
    $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
});
