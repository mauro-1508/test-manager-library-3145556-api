<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;

final class Paginator
{
    /**
     * @param list<mixed> $all
     */
    public static function slice(array $all, PageRequest $request): Page
    {
        return Page::of(array_slice($all, $request->offset(), $request->size), $request, count($all));
    }
}
