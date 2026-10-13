<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\Category;

interface CategoryRepository
{
    /**
     * @return list<Category> ordered by name
     */
    public function all(): array;

    public function find(string $id): ?Category;
}
