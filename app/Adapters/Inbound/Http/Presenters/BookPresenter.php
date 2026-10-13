<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Presenters;

use App\Application\Dto\BookView;
use App\Application\Dto\Category;

final class BookPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(BookView $view): array
    {
        $book = $view->book;

        return [
            'id' => $book->id,
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn->value,
            'category' => self::category($view->category),
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    public static function category(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name];
    }
}
