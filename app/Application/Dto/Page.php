<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class Page
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(public array $items, public int $page, public int $size, public int $total)
    {
    }

    /**
     * @param list<mixed> $items
     */
    public static function of(array $items, PageRequest $request, int $total): self
    {
        return new self($items, $request->page, $request->size, $total);
    }

    public function map(callable $mapper): self
    {
        return new self(array_values(array_map($mapper, $this->items)), $this->page, $this->size, $this->total);
    }
}
