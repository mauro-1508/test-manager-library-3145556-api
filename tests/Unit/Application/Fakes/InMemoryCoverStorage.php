<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Ports\Outbound\CoverStorage;
use Closure;

final class InMemoryCoverStorage implements CoverStorage
{
    /** @var array<string, string> */
    private array $binaries = [];

    private int $counter = 0;

    /** @var Closure(string): void|null runs right before a binary is deleted */
    public ?Closure $beforeDelete = null;

    public function store(string $bytes, string $mimeType): string
    {
        $key = 'cover-' . ++$this->counter;
        $this->binaries[$key] = $bytes;

        return $key;
    }

    public function delete(string $key): void
    {
        if ($this->beforeDelete !== null) {
            ($this->beforeDelete)($key);
        }
        unset($this->binaries[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->binaries[$key]);
    }

    public function count(): int
    {
        return count($this->binaries);
    }
}
