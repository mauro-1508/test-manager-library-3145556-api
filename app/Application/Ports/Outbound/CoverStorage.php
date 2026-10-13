<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface CoverStorage
{
    /**
     * Stores the binary and returns the opaque key that identifies it.
     */
    public function store(string $bytes, string $mimeType): string;

    public function delete(string $key): void;
}
