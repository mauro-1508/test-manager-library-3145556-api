<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Storage;

use App\Application\Ports\Outbound\CoverStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Keeps cover binaries on a filesystem disk. The key is random and opaque: it never contains a path or an extension.
 */
final class DiskCoverStorage implements CoverStorage
{
    private const KEY_BYTES = 16;

    public function __construct(private readonly Filesystem $disk)
    {
    }

    public function store(string $bytes, string $mimeType): string
    {
        $key = bin2hex(random_bytes(self::KEY_BYTES));
        if (!$this->disk->put($key, $bytes)) {
            throw new RuntimeException('The cover could not be written to the disk.');
        }

        return $key;
    }

    public function delete(string $key): void
    {
        $this->disk->delete($key);
    }
}
