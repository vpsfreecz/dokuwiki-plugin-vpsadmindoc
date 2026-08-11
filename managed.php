<?php

final class VpsAdminDocManagedPage
{
    private const ATTRIBUTES = ['source', 'test'];

    public static function parseTag(string $tag): ?array
    {
        if (!preg_match('/\A<kb-managed(?<attributes>.*?)\/>\z/s', $tag, $matches)) {
            return null;
        }

        $attributes = [];
        $text = $matches['attributes'];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            if (preg_match('/\G\s*\z/s', $text, $ignored, 0, $offset)) {
                break;
            }

            if (!preg_match(
                '/\G\s+([a-z]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s',
                $text,
                $attribute,
                PREG_UNMATCHED_AS_NULL,
                $offset
            )) {
                return null;
            }

            $name = $attribute[1];
            if (!in_array($name, self::ATTRIBUTES, true) || array_key_exists($name, $attributes)) {
                return null;
            }

            $attributes[$name] = $attribute[2] !== null ? $attribute[2] : $attribute[3];
            $offset += strlen($attribute[0]);
        }

        ksort($attributes);
        return self::isValid($attributes) ? $attributes : null;
    }

    public static function isValid($managed): bool
    {
        if (!is_array($managed) || array_keys($managed) !== self::ATTRIBUTES) {
            return false;
        }

        foreach (self::ATTRIBUTES as $attribute) {
            if (!is_string($managed[$attribute]) || !self::isGitHubBlobUrl($managed[$attribute])) {
                return false;
            }
        }

        return true;
    }

    private static function isGitHubBlobUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'path'])) {
            return false;
        }
        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'github.com') {
            return false;
        }

        $path = $parts['path'] ?? '';
        if (!preg_match(
            '#\A/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/blob/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+\z#D',
            $path
        )) {
            return false;
        }

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
