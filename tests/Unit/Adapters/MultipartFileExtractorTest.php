<?php

use App\Adapters\Inbound\Http\Requests\MultipartFileExtractor;

it('extracts the file part of a raw multipart PUT body byte for byte', function (): void {
    $bytes = "\x89PNG\r\n\x1a\n" . random_bytes(64);
    $body = "--XBOUND\r\nContent-Disposition: form-data; name=\"file\"; filename=\"a.png\"\r\n"
        . "Content-Type: image/png\r\n\r\n" . $bytes . "\r\n--XBOUND--\r\n";

    $file = MultipartFileExtractor::extract('multipart/form-data; boundary=XBOUND', $body, 'file');

    expect($file)->not->toBeNull()
        ->and($file->getClientOriginalName())->toBe('a.png')
        ->and(file_get_contents($file->getRealPath()))->toBe($bytes);
});

it('returns null when there is no file part or no boundary', function (): void {
    expect(MultipartFileExtractor::extract('multipart/form-data', 'x', 'file'))->toBeNull()
        ->and(MultipartFileExtractor::extract('multipart/form-data; boundary=B', "--B\r\nContent-Disposition: form-data; name=\"other\"\r\n\r\nv\r\n--B--", 'file'))->toBeNull();
});
