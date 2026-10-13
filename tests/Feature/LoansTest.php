<?php

use App\Domain\Role;
use Illuminate\Support\Str;
use Tests\Feature\Support\Api;

beforeEach(function (): void {
    $this->api = Api::boot();
    $this->admin = $this->api->as(Role::Admin);
    $this->librarian = $this->api->as(Role::Librarian);
    $this->library = $this->api->library;
});

function lendBooks(object $test, string $memberId, array $bookIds, ?array $headers = null): Illuminate\Testing\TestResponse
{
    return $test->postJson('/api/v1/loans', [
        'member_id' => $memberId,
        'items' => array_map(static fn (string $id): array => ['book_id' => $id], $bookIds),
    ], $headers ?? $test->librarian);
}

it('creates and searches members, and shows active_loans on the detail', function (): void {
    $created = $this->postJson('/api/v1/members', ['full_name' => ' Ana Pérez ', 'document_number' => 'CC-100'], $this->librarian)
        ->assertCreated()
        ->assertJsonPath('full_name', 'Ana Pérez')
        ->assertJsonPath('document_number', 'CC-100');
    $book = $this->library->addBook();
    lendBooks($this, $created->json('id'), [$book->id])->assertCreated();

    $this->getJson("/api/v1/members/{$created->json('id')}", $this->admin)->assertOk()->assertJsonPath('active_loans', 1);
    $this->getJson('/api/v1/members?q=ana&page=1&size=5', $this->librarian)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('size', 5)
        ->assertJsonMissingPath('items.0.active_loans');
});

it('rejects a repeated document with DOCUMENT_TAKEN 409 and a missing name with VALIDATION_ERROR', function (): void {
    $body = ['full_name' => 'Ana', 'document_number' => 'CC-1'];
    $this->postJson('/api/v1/members', $body, $this->librarian)->assertCreated();

    $this->postJson('/api/v1/members', $body, $this->librarian)->assertStatus(409)->assertJsonPath('code', 'DOCUMENT_TAKEN');
    $this->postJson('/api/v1/members', ['document_number' => 'CC-2'], $this->librarian)
        ->assertStatus(422)
        ->assertJsonPath('details.0.field', 'full_name');
});

it('answers NOT_FOUND for an unknown member', function (): void {
    $this->getJson('/api/v1/members/' . Str::uuid(), $this->librarian)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
});

it('registers a loan with the contract shape', function (): void {
    $member = $this->library->addMember();
    $book = $this->library->addBook('Cien años de soledad', 4);

    $response = lendBooks($this, $member->id, [$book->id])
        ->assertCreated()
        ->assertJsonPath('member_id', $member->id)
        ->assertJsonPath('loaned_at', '2026-10-14T14:15:00Z')
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('items.0.book_title', 'Cien años de soledad')
        ->assertJsonPath('items.0.category_name', 'Novela')
        ->assertJsonPath('items.0.due_at', '2026-10-28T14:15:00Z')
        ->assertJsonPath('items.0.returned_at', null);

    expect(array_keys($response->json('items.0')))
        ->toBe(['id', 'book_id', 'book_title', 'isbn', 'category_name', 'due_at', 'returned_at']);
    $this->getJson("/api/v1/books/{$book->id}", $this->librarian)->assertJsonPath('available_copies', 3);
});

it('rejects a loan when no copies are left with BOOK_NOT_AVAILABLE 409', function (): void {
    $book = $this->library->addBook('Rayuela', 1);
    lendBooks($this, $this->library->addMember()->id, [$book->id])->assertCreated();

    lendBooks($this, $this->library->addMember()->id, [$book->id])
        ->assertStatus(409)
        ->assertJsonPath('code', 'BOOK_NOT_AVAILABLE');
});

it('rejects a repeated book with DUPLICATE_BOOK_IN_LOAN 422', function (): void {
    $book = $this->library->addBook();

    lendBooks($this, $this->library->addMember()->id, [$book->id, $book->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
});

it('rejects more than three unreturned lines with MEMBER_LIMIT_EXCEEDED 422', function (): void {
    $member = $this->library->addMember();
    lendBooks($this, $member->id, [$this->library->addBook()->id, $this->library->addBook()->id, $this->library->addBook()->id])
        ->assertCreated();

    lendBooks($this, $member->id, [$this->library->addBook()->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED');
});

it('rejects an empty or oversized item list and malformed ids with VALIDATION_ERROR', function (): void {
    $member = $this->library->addMember();

    lendBooks($this, $member->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    lendBooks($this, $member->id, array_map(fn () => $this->library->addBook()->id, range(1, 4)))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->postJson('/api/v1/loans', ['member_id' => 'x', 'items' => [['book_id' => 'y']]], $this->librarian)
        ->assertStatus(422)
        ->assertJsonPath('details.0.field', 'member_id')
        ->assertJsonPath('details.1.field', 'items.0.book_id');
});

it('answers NOT_FOUND when the member or a book of the loan does not exist', function (): void {
    $book = $this->library->addBook();

    lendBooks($this, (string) Str::uuid(), [$book->id])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    lendBooks($this, $this->library->addMember()->id, [(string) Str::uuid()])->assertStatus(404);
});

it('blocks a member with overdue lines with MEMBER_HAS_OVERDUE 409 and lists them in /loans/overdue', function (): void {
    $member = $this->library->addMember('Ana Pérez');
    $loan = lendBooks($this, $member->id, [$this->library->addBook('Rayuela', 2)->id])->assertCreated();
    $this->library->clock->set('2026-11-02T14:15:00Z');

    lendBooks($this, $member->id, [$this->library->addBook()->id])
        ->assertStatus(409)
        ->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');
    $overdue = $this->getJson('/api/v1/loans/overdue', $this->librarian)->assertOk();
    expect($overdue->json('items'))->toBe([[
        'loan_id' => $loan->json('id'),
        'item_id' => $loan->json('items.0.id'),
        'book_title' => 'Rayuela',
        'member_name' => 'Ana Pérez',
        'due_at' => '2026-10-28T14:15:00Z',
        'days_late' => 5,
    ]]);
    $this->getJson("/api/v1/loans/{$loan->json('id')}", $this->librarian)->assertJsonPath('status', 'overdue');
});

it('returns an item once and then answers ITEM_ALREADY_RETURNED 409', function (): void {
    $book = $this->library->addBook('Rayuela', 1);
    $loan = lendBooks($this, $this->library->addMember()->id, [$book->id])->assertCreated();
    $url = "/api/v1/loans/{$loan->json('id')}/items/{$loan->json('items.0.id')}/return";

    $this->postJson($url, [], $this->librarian)
        ->assertOk()
        ->assertJsonPath('id', $loan->json('items.0.id'))
        ->assertJsonPath('returned_at', '2026-10-14T14:15:00Z');
    $this->postJson($url, [], $this->librarian)->assertStatus(409)->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');
    $this->getJson("/api/v1/loans/{$loan->json('id')}", $this->librarian)->assertJsonPath('status', 'closed');
    $this->getJson("/api/v1/books/{$book->id}", $this->librarian)->assertJsonPath('available_copies', 1);
});

it('answers NOT_FOUND returning an unknown loan or item', function (): void {
    $loan = lendBooks($this, $this->library->addMember()->id, [$this->library->addBook()->id])->assertCreated();

    $this->postJson("/api/v1/loans/" . Str::uuid() . '/items/' . Str::uuid() . '/return', [], $this->librarian)->assertStatus(404);
    $this->postJson("/api/v1/loans/{$loan->json('id')}/items/" . Str::uuid() . '/return', [], $this->librarian)
        ->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    $this->getJson('/api/v1/loans/' . Str::uuid(), $this->librarian)->assertStatus(404);
});

it('lists loans filtered by status, member and dates, and rejects an inverted range', function (): void {
    $ana = $this->library->addMember('Ana');
    $luis = $this->library->addMember('Luis');
    lendBooks($this, $ana->id, [$this->library->addBook()->id])->assertCreated();
    lendBooks($this, $luis->id, [$this->library->addBook()->id])->assertCreated();

    $this->getJson('/api/v1/loans', $this->librarian)->assertOk()->assertJsonPath('total', 2);
    $this->getJson("/api/v1/loans?member_id={$ana->id}&status=open&from=2026-10-14&to=2026-10-14", $this->librarian)
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.member_id', $ana->id);
    $this->getJson('/api/v1/loans?status=closed', $this->librarian)->assertOk()->assertJsonPath('total', 0);
    $this->getJson('/api/v1/loans?from=2026-10-20&to=2026-10-10', $this->librarian)
        ->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
    $this->getJson('/api/v1/loans?status=lost&from=14-10-2026', $this->librarian)
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('serves /loans/overdue before /loans/{id}', function (): void {
    $this->getJson('/api/v1/loans/overdue', $this->admin)->assertOk()->assertExactJson(['items' => []]);
});

it('builds the most-borrowed report for admins only, with frozen titles and no people', function (): void {
    $rayuela = $this->library->addBook('Rayuela', 5);
    $other = $this->library->addBook('Ficciones', 5);
    lendBooks($this, $this->library->addMember()->id, [$rayuela->id, $other->id])->assertCreated();
    lendBooks($this, $this->library->addMember()->id, [$rayuela->id])->assertCreated();

    $response = $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->admin)
        ->assertOk()
        ->assertJsonPath('from', '2026-10-01')
        ->assertJsonPath('to', '2026-10-31');

    expect($response->json('rows'))->toBe([
        ['book_id' => $rayuela->id, 'book_title' => 'Rayuela', 'category_name' => 'Novela', 'times_borrowed' => 2],
        ['book_id' => $other->id, 'book_title' => 'Ficciones', 'category_name' => 'Novela', 'times_borrowed' => 1],
    ]);
    expect($response->getContent())->not->toContain('member')->not->toContain('Ana');
    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->librarian)->assertStatus(403);
});

it('validates the report range', function (): void {
    $this->getJson('/api/v1/reports/most-borrowed', $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('details.0.field', 'from')
        ->assertJsonPath('details.1.field', 'to');
    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01', $this->admin)
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_DATE_RANGE');
});

it('requires a token on every protected route', function (): void {
    $id = (string) Str::uuid();
    $routes = [
        ['GET', '/categories'], ['GET', '/books'], ['GET', "/books/{$id}"], ['POST', '/books'], ['PUT', "/books/{$id}"],
        ['DELETE', "/books/{$id}"], ['PUT', "/books/{$id}/cover"], ['DELETE', "/books/{$id}/cover"], ['POST', '/users'],
        ['GET', '/members'], ['GET', "/members/{$id}"], ['POST', '/members'], ['POST', '/loans'], ['GET', '/loans'],
        ['GET', '/loans/overdue'], ['GET', "/loans/{$id}"], ['POST', "/loans/{$id}/items/{$id}/return"],
        ['GET', '/reports/most-borrowed'],
    ];

    foreach ($routes as [$method, $path]) {
        $this->json($method, "/api/v1{$path}")->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }
});
