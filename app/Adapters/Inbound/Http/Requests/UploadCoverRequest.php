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

    protected function prepareForValidation(): void
    {
        $contentType = (string) $this->header('Content-Type');
        if ($this->isMethod('PUT') && $this->file('file') === null && stripos($contentType, 'multipart/form-data') === 0) {
            $file = MultipartFileExtractor::extract($contentType, $this->getContent(), 'file');
            if ($file !== null) {
                $this->files->set('file', $file);
            }
        }
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
