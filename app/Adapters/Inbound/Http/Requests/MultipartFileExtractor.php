<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PHP only fills $_FILES for POST, so a real PUT with multipart/form-data arrives with its files unparsed.
 * This extracts one named file part from the raw body into a temporary file.
 */
final class MultipartFileExtractor
{
    private const BOUNDARY_PATTERN = '/boundary=(?:"([^"]+)"|([^;\s]+))/i';
    private const HEADER_BODY_SEPARATOR = "\r\n\r\n";
    private const LINE_BREAK = "\r\n";

    public static function extract(string $contentType, string $body, string $fieldName): ?UploadedFile
    {
        if (preg_match(self::BOUNDARY_PATTERN, $contentType, $matches) !== 1) {
            return null;
        }
        $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];

        foreach (explode('--' . $boundary, $body) as $part) {
            $file = self::fileFromPart(ltrim($part, self::LINE_BREAK), $fieldName);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    private static function fileFromPart(string $part, string $fieldName): ?UploadedFile
    {
        $separatorAt = strpos($part, self::HEADER_BODY_SEPARATOR);
        if ($separatorAt === false) {
            return null;
        }
        $headers = substr($part, 0, $separatorAt);
        $pattern = '/name="' . preg_quote($fieldName, '/') . '"; filename="([^"]*)"/i';
        if (preg_match($pattern, $headers, $matches) !== 1) {
            return null;
        }
        $content = substr($part, $separatorAt + strlen(self::HEADER_BODY_SEPARATOR));
        if (str_ends_with($content, self::LINE_BREAK)) {
            $content = substr($content, 0, -strlen(self::LINE_BREAK));
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $matches[1], null, null, true);
    }
}
