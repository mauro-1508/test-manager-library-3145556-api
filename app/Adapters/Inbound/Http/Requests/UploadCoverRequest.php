<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use finfo;

final class UploadCoverRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file']];
    }

    public function bytes(): string
    {
        return (string) file_get_contents($this->file('file')->getRealPath());
    }

    /**
     * The type is read from the content, never from the extension or the client-declared type.
     */
    public function detectedMimeType(string $bytes): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    }
}
