<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Category;
use App\Application\Ports\Outbound\CategoryRepository;

final class ListCategoriesService
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @return list<Category>
     */
    public function execute(): array
    {
        return $this->categories->all();
    }
}
