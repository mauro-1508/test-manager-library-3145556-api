<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Category;
use App\Application\Ports\Outbound\CategoryRepository;
use Illuminate\Support\Facades\DB;

final class PgCategoryRepository implements CategoryRepository
{
    public function all(): array
    {
        return DB::table('library.category')->orderBy('name')->get()
            ->map(static fn (object $row): Category => new Category($row->id, $row->name))
            ->all();
    }

    public function find(string $id): ?Category
    {
        $row = DB::table('library.category')->where('id', $id)->first();

        return $row === null ? null : new Category($row->id, $row->name);
    }
}
