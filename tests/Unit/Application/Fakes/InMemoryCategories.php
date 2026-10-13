<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\Category;
use App\Application\Ports\Outbound\CategoryRepository;

final class InMemoryCategories implements CategoryRepository
{
    public const NOVEL_ID = '11111111-1111-4111-8111-111111111111';
    public const SCIENCE_ID = '22222222-2222-4222-8222-222222222222';
    public const TECHNOLOGY_ID = '33333333-3333-4333-8333-333333333333';
    public const HISTORY_ID = '44444444-4444-4444-8444-444444444444';
    public const CHILDREN_ID = '55555555-5555-4555-8555-555555555555';

    /** @var array<string, Category> */
    private array $byId = [];

    public function __construct()
    {
        foreach ([
            self::NOVEL_ID => 'Novela',
            self::SCIENCE_ID => 'Ciencia',
            self::TECHNOLOGY_ID => 'Tecnología',
            self::HISTORY_ID => 'Historia',
            self::CHILDREN_ID => 'Infantil',
        ] as $id => $name) {
            $this->byId[$id] = new Category($id, $name);
        }
    }

    public function all(): array
    {
        $all = array_values($this->byId);
        usort($all, static fn (Category $a, Category $b): int => strcmp($a->name, $b->name));

        return $all;
    }

    public function find(string $id): ?Category
    {
        return $this->byId[$id] ?? null;
    }

    public function rename(string $id, string $newName): void
    {
        $this->byId[$id] = new Category($id, $newName);
    }
}
