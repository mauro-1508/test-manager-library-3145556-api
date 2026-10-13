<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Errors\IsbnTaken;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\DomainValidationError;
use App\Domain\Isbn;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgBookRepository implements BookRepository
{
    public function save(Book $book): void
    {
        try {
            if ($this->updateExisting($book) === 0) {
                DB::table('library.book')->insert($this->columns($book) + [
                    'id' => $book->id,
                    'available_copies' => $book->availableCopies(),
                ]);
            }
        } catch (QueryException $error) {
            throw match (true) {
                PgErrors::isUniqueViolation($error) => new IsbnTaken(),
                PgErrors::isCheckViolation($error) => new DomainValidationError('El nuevo total deja los ejemplares disponibles por debajo de cero.'),
                default => $error,
            };
        }
    }

    /**
     * Updates an existing row. available_copies is never overwritten with the value read earlier:
     * it only moves by the change in total, computed atomically in SQL, so a concurrent loan is not lost.
     */
    private function updateExisting(Book $book): int
    {
        return DB::table('library.book')->where('id', $book->id)->update($this->columns($book) + [
            'available_copies' => DB::raw('available_copies + (' . (int) $book->totalCopies() . ' - total_copies)'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Book $book): array
    {
        return [
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn->value,
            'category_id' => $book->categoryId(),
            'total_copies' => $book->totalCopies(),
            'cover_key' => $book->coverKey(),
            'deleted_at' => $book->deletedAt() === null ? null : PgTime::toDb($book->deletedAt()),
        ];
    }

    public function findActive(string $id): ?Book
    {
        $row = $this->active()->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->active()->whereIn('id', $ids)->get()
            ->map(static fn (object $row): Book => self::hydrate($row))
            ->all();
    }

    public function search(?string $text, ?string $categoryId, PageRequest $request): Page
    {
        $query = $this->active();
        if ($text !== null && trim($text) !== '') {
            $pattern = PgTime::containsPattern(trim($text));
            $query->whereRaw('(title ILIKE ? OR author ILIKE ?)', [$pattern, $pattern]);
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('title')->orderBy('id')
            ->offset($request->offset())->limit($request->size)->get();

        return Page::of($rows->map(static fn (object $row): Book => self::hydrate($row))->all(), $request, $total);
    }

    public function isIsbnTakenByActiveBook(Isbn $isbn): bool
    {
        return $this->active()->where('isbn', $isbn->value)->exists();
    }

    /**
     * The single place where the catalogue hides deactivated books (ADR-003).
     */
    private function active(): Builder
    {
        return DB::table('library.book')->whereNull('deleted_at');
    }

    private static function hydrate(object $row): Book
    {
        return new Book(
            $row->id,
            $row->title,
            $row->author,
            new Isbn($row->isbn),
            $row->category_id,
            (int) $row->total_copies,
            (int) $row->available_copies,
            $row->cover_key,
            PgTime::fromDbNullable($row->deleted_at),
        );
    }
}
