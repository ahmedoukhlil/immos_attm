<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ReferenceCache
{
    public static function key(string $domain, string $key): string
    {
        $version = Cache::rememberForever(
            self::versionKey($domain),
            fn (): string => (string) Str::uuid(),
        );

        return "references:{$domain}:{$version}:{$key}";
    }

    public static function invalidate(string ...$domains): void
    {
        foreach (array_unique($domains) as $domain) {
            Cache::forever(self::versionKey($domain), (string) Str::uuid());
        }
    }

    private static function versionKey(string $domain): string
    {
        return "references:{$domain}:version";
    }
}
