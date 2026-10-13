<?php

declare(strict_types=1);

namespace App\Domain;

final class Uuid
{
    private const VERSION_4_MASK = 0x40;
    private const VARIANT_MASK = 0x80;

    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | self::VERSION_4_MASK);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | self::VARIANT_MASK);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
