<?php

declare(strict_types=1);

namespace Podquilt\Tools\ContextSystem;

final class PathMatcher
{
    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function matches(string $path, string $pattern): bool
    {
        $normalizedPath = self::normalize($path);
        $normalizedPattern = self::normalize($pattern);

        if ($normalizedPattern === $normalizedPath) {
            return true;
        }

        $regex = self::globToRegex($normalizedPattern);

        return (bool) preg_match($regex, $normalizedPath);
    }

    /**
     * @param list<string> $patterns
     */
    public static function anyMatches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function isWithin(string $path, string $root): bool
    {
        $normalizedPath = self::normalize($path);
        $normalizedRoot = rtrim(self::normalize($root), '/');

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private static function globToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '~');
        $escaped = str_replace('\*\*', '::DOUBLE_STAR::', $escaped);
        $escaped = str_replace('\*', '[^/]*', $escaped);
        $escaped = str_replace('::DOUBLE_STAR::', '.*', $escaped);

        return '~^' . $escaped . '$~';
    }
}
