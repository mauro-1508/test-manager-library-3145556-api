<?php

use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Role;
use Tests\Feature\Support\Api;

beforeEach(function (): void {
    $this->api = Api::boot();
});

function assertErrorBody(Illuminate\Testing\TestResponse $response, int $status, string $code): void
{
    $response->assertStatus($status);
    expect(array_keys($response->json()))->toBe(['code', 'message', 'details'])
        ->and($response->json('code'))->toBe($code)
        ->and($response->json('message'))->toBeString()->not->toBeEmpty()
        ->and($response->json('details'))->toBeArray();
}

it('answers an unknown route with the single error body NOT_FOUND', function (): void {
    assertErrorBody($this->getJson('/api/v1/nothing-here'), 404, 'NOT_FOUND');
});

it('answers a wrong method with the single error body and status 405', function (): void {
    assertErrorBody($this->deleteJson('/api/v1/health'), 405, 'METHOD_NOT_ALLOWED');
});

it('answers a malformed JSON body with a 422 in the single error body', function (): void {
    $response = $this->call('POST', '/api/v1/auth/login', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], '{"username": ');

    assertErrorBody($response, 422, 'VALIDATION_ERROR');
});

it('answers an unexpected failure with a generic Spanish 500 that leaks nothing', function (): void {
    $this->app->bind(CategoryRepository::class, static function (): never {
        throw new RuntimeException('SQLSTATE[08006] password=hunter2 at /var/www/secret.php');
    });

    $response = $this->getJson('/api/v1/categories', $this->api->as(Role::Admin));

    assertErrorBody($response, 500, 'INTERNAL_ERROR');
    expect($response->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('hunter2')
        ->not->toContain('secret.php')
        ->not->toContain('trace');
});

it('uses the single error body for authorization failures too', function (): void {
    assertErrorBody($this->getJson('/api/v1/categories'), 401, 'INVALID_CREDENTIALS');
    assertErrorBody(
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-02', $this->api->as(Role::Librarian)),
        403,
        'FORBIDDEN_ROLE',
    );
});
