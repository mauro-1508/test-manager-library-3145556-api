<?php

use App\Domain\Role;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Support\Api;
use Tests\Unit\Application\Fakes\InMemoryCategories;

const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
const TWO_MB = 2 * 1024 * 1024;

function pngOfSize(int $bytes): string
{
    return str_pad(base64_decode(ONE_PIXEL_PNG), $bytes, "\0");
}

beforeEach(function (): void {
    $this->api = Api::boot();
    $this->admin = $this->api->as(Role::Admin);
    $this->librarian = $this->api->as(Role::Librarian);
    $this->newBook = [
        'title' => 'Cien años de soledad',
        'author' => 'Gabriel García Márquez',
        'isbn' => '9780307474728',
        'category_id' => InMemoryCategories::NOVEL_ID,
        'total_copies' => 4,
    ];
});

function putCoverFile(object $test, string $bookId, UploadedFile $file, array $headers): Illuminate\Testing\TestResponse
{
    return $test->call('PUT', "/api/v1/books/{$bookId}/cover", [], [], ['file' => $file], [
        'HTTP_AUTHORIZATION' => $headers['Authorization'],
        'HTTP_ACCEPT' => 'application/json',
    ]);
}

it('lists the five categories ordered by name to any authenticated user', function (): void {
    $response = $this->getJson('/api/v1/categories', $this->librarian)->assertOk();

    expect(array_column($response->json('items'), 'name'))
        ->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología'])
        ->and(array_keys($response->json('items.0')))->toBe(['id', 'name']);
});

it('creates a book with the contract shape and server-computed availability', function (): void {
    $this->postJson('/api/v1/books', $this->newBook, $this->admin)
        ->assertCreated()
        ->assertJsonStructure(['id', 'title', 'author', 'isbn', 'category' => ['id', 'name'], 'total_copies', 'available_copies', 'cover_key'])
        ->assertJsonPath('category.name', 'Novela')
        ->assertJsonPath('total_copies', 4)
        ->assertJsonPath('available_copies', 4)
        ->assertJsonPath('cover_key', null);
});

it('forbids a librarian from writing books', function (): void {
    $book = $this->api->library->addBook();

    $this->postJson('/api/v1/books', $this->newBook, $this->librarian)->assertStatus(403);
    $this->putJson("/api/v1/books/{$book->id}", $this->newBook, $this->librarian)->assertStatus(403);
    $this->deleteJson("/api/v1/books/{$book->id}", [], $this->librarian)->assertStatus(403);
    $this->deleteJson("/api/v1/books/{$book->id}/cover", [], $this->librarian)->assertStatus(403);
});

it('does not reveal whether a book exists to a role that cannot touch it', function (): void {
    $missing = Illuminate\Support\Str::uuid()->toString();
    $existing = $this->api->library->addBook();

    $forMissing = $this->deleteJson("/api/v1/books/{$missing}", [], $this->librarian);
    $forExisting = $this->deleteJson("/api/v1/books/{$existing->id}", [], $this->librarian);

    expect($forMissing->status())->toBe(403)->and($forExisting->json())->toBe($forMissing->json());
});

it('rejects a repeated ISBN with ISBN_TAKEN 409', function (): void {
    $this->postJson('/api/v1/books', $this->newBook, $this->admin)->assertCreated();

    $this->postJson('/api/v1/books', $this->newBook, $this->admin)
        ->assertStatus(409)
        ->assertJsonPath('code', 'ISBN_TAKEN');
});

it('rejects invalid book fields with VALIDATION_ERROR and a details entry per field', function (): void {
    $response = $this->postJson('/api/v1/books', ['title' => '', 'isbn' => 'abc', 'total_copies' => 'many'], $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    expect(array_column($response->json('details'), 'field'))
        ->toContain('title', 'author', 'category_id', 'total_copies');
    $this->postJson('/api/v1/books', ['isbn' => 'abc'] + $this->newBook, $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->postJson('/api/v1/books', ['total_copies' => 0] + $this->newBook, $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('shows, searches with pagination, replaces and soft-deletes a book', function (): void {
    $book = $this->api->library->addBook('Rayuela', 3);
    $this->api->library->addBook('Cien años de soledad', 3);

    $this->getJson("/api/v1/books/{$book->id}", $this->librarian)->assertOk()->assertJsonPath('title', 'Rayuela');
    $this->getJson('/api/v1/books?q=rayu&page=1&size=10', $this->librarian)
        ->assertOk()
        ->assertJsonPath('page', 1)
        ->assertJsonPath('size', 10)
        ->assertJsonPath('total', 1)
        ->assertJsonPath('items.0.id', $book->id);
    $this->getJson('/api/v1/books?category_id=' . InMemoryCategories::SCIENCE_ID, $this->librarian)
        ->assertOk()->assertJsonPath('total', 0);

    $this->putJson("/api/v1/books/{$book->id}", [
        'title' => 'Rayuela (ed.)', 'author' => 'Julio Cortázar',
        'category_id' => InMemoryCategories::SCIENCE_ID, 'total_copies' => 5,
    ], $this->admin)->assertOk()->assertJsonPath('title', 'Rayuela (ed.)')->assertJsonPath('total_copies', 5);

    $this->deleteJson("/api/v1/books/{$book->id}", [], $this->admin)->assertNoContent();
    $this->getJson("/api/v1/books/{$book->id}", $this->admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
});

it('rejects a page size above 50', function (): void {
    $this->getJson('/api/v1/books?size=51', $this->librarian)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('details.0.field', 'size');
});

it('answers NOT_FOUND 404 for unknown books and for ids that are not UUIDs', function (): void {
    $missing = Illuminate\Support\Str::uuid()->toString();

    $this->getJson("/api/v1/books/{$missing}", $this->admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    $this->putJson("/api/v1/books/{$missing}", $this->newBook, $this->admin)->assertStatus(404);
    $this->deleteJson("/api/v1/books/{$missing}", [], $this->admin)->assertStatus(404);
    $this->getJson('/api/v1/books/not-a-uuid', $this->admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
});

it('refuses to delete a book with open loans with BOOK_HAS_OPEN_LOANS 409', function (): void {
    $book = $this->api->library->addBook();
    $member = $this->api->library->addMember();
    $this->postJson('/api/v1/loans', ['member_id' => $member->id, 'items' => [['book_id' => $book->id]]], $this->librarian)
        ->assertCreated();

    $this->deleteJson("/api/v1/books/{$book->id}", [], $this->admin)
        ->assertStatus(409)
        ->assertJsonPath('code', 'BOOK_HAS_OPEN_LOANS');
});

it('accepts a PNG cover of exactly 2 MB and exposes an opaque cover_key', function (): void {
    $book = $this->api->library->addBook();
    $file = UploadedFile::fake()->createWithContent('cover.png', pngOfSize(TWO_MB));

    $response = putCoverFile($this, $book->id, $file, $this->admin)->assertOk();

    expect($response->json('cover_key'))->toBeString()->not->toBeEmpty()
        ->and($this->api->library->covers->has($response->json('cover_key')))->toBeTrue();
});

it('rejects a 3 MB cover with VALIDATION_ERROR 422', function (): void {
    $book = $this->api->library->addBook();
    $file = UploadedFile::fake()->createWithContent('cover.png', pngOfSize(3 * 1024 * 1024));

    putCoverFile($this, $book->id, $file, $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
    expect($this->api->library->covers->count())->toBe(0);
});

it('validates the cover type by content, not by extension', function (): void {
    $book = $this->api->library->addBook();
    $disguised = UploadedFile::fake()->createWithContent('cover.png', 'just text, not an image');
    $jpegNamedTxt = UploadedFile::fake()->createWithContent('cover.txt', pngOfSize(100));

    putCoverFile($this, $book->id, $disguised, $this->admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    putCoverFile($this, $book->id, $jpegNamedTxt, $this->admin)->assertOk();
});

it('rejects a cover request without the file field', function (): void {
    $book = $this->api->library->addBook();

    $this->putJson("/api/v1/books/{$book->id}/cover", [], $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('details.0.field', 'file');
});

it('removes a cover and leaves cover_key null', function (): void {
    $book = $this->api->library->addBook();
    putCoverFile($this, $book->id, UploadedFile::fake()->createWithContent('c.png', pngOfSize(500)), $this->admin)->assertOk();

    $this->deleteJson("/api/v1/books/{$book->id}/cover", [], $this->admin)
        ->assertOk()
        ->assertJsonPath('cover_key', null);
    expect($this->api->library->covers->count())->toBe(0);
});
