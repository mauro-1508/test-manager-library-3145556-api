<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Presenters;

use App\Application\Dto\Page;

final class PagePresenter
{
    /**
     * @param callable(mixed): array<string, mixed> $presentItem
     * @return array{items: list<array<string, mixed>>, page: int, size: int, total: int}
     */
    public static function present(Page $page, callable $presentItem): array
    {
        return [
            'items' => array_values(array_map($presentItem, $page->items)),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ];
    }
}
