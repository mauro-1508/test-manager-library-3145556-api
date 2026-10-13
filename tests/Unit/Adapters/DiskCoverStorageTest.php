<?php

use App\Adapters\Outbound\Storage\DiskCoverStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

function temporaryCoverDirectory(): string
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'covers-' . bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

it('stores the binary under an opaque random key and deletes it', function (): void {
    $directory = temporaryCoverDirectory();
    $adapter = new LocalFilesystemAdapter($directory);
    $disk = new FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $directory]);
    $storage = new DiskCoverStorage($disk);

    $key = $storage->store('binary-bytes', 'image/png');
    $other = $storage->store('binary-bytes', 'image/png');

    expect($key)->toMatch('/^[0-9a-f]{32}$/')->not->toBe($other)
        ->and(file_get_contents($directory . DIRECTORY_SEPARATOR . $key))->toBe('binary-bytes');

    $storage->delete($key);
    $storage->delete($other);
    expect(file_exists($directory . DIRECTORY_SEPARATOR . $key))->toBeFalse();
    rmdir($directory);
});
