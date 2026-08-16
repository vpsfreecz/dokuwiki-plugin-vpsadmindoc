<?php

final class VpsAdminDocManagedPage
{
    private const ATTRIBUTES = ['source', 'test'];
    private const MAX_ATTRIBUTE_LENGTH = 2048;
    private const TEST_PATTERN = '[a-z0-9][a-z0-9_.-]*(?:/[a-z0-9][a-z0-9_.-]*)*#\\*';

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

        if (!is_string($managed['source']) || !is_string($managed['test'])) {
            return false;
        }

        return (self::isRelativeSource($managed['source']) && self::isTestPattern($managed['test']))
            || (self::isGitHubBlobUrl($managed['source'])
                && self::isGitHubBlobUrl($managed['test']));
    }

    public static function isLegacy($managed): bool
    {
        return self::isValid($managed) && self::isGitHubBlobUrl($managed['source']);
    }

    public static function testSource(string $pattern): ?string
    {
        if (!self::isTestPattern($pattern)) {
            return null;
        }

        return 'tests/suite/' . substr($pattern, 0, -2) . '.nix';
    }

    private static function isRelativeSource(string $path): bool
    {
        if ($path === '' || strlen($path) > self::MAX_ATTRIBUTE_LENGTH) {
            return false;
        }

        if (!preg_match('#\A[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*\z#D', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private static function isTestPattern(string $pattern): bool
    {
        return strlen($pattern) <= self::MAX_ATTRIBUTE_LENGTH
            && preg_match('~\A' . self::TEST_PATTERN . '\z~D', $pattern) === 1;
    }

    private static function isGitHubBlobUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_ATTRIBUTE_LENGTH
            || preg_match('/[\x00-\x20\x7f]/', $url)) {
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

final class VpsAdminDocManagedRepository
{
    private const MAX_CONFIG_LENGTH = 4096;
    private const MAX_REF_FILE_LENGTH = 128;

    public static function resolve(
        array $managed,
        string $repositoryUrl,
        string $staticRef,
        string $refFile
    ): ?array {
        if (!VpsAdminDocManagedPage::isValid($managed)) {
            return null;
        }

        if (VpsAdminDocManagedPage::isLegacy($managed)) {
            return [
                'source' => $managed['source'],
                'test' => $managed['test'],
                'test_selector' => self::legacyTestSelector($managed['test']),
            ];
        }

        $repository = self::repositoryUrl($repositoryUrl);
        $ref = self::repositoryRef($staticRef, $refFile);
        $testSource = VpsAdminDocManagedPage::testSource($managed['test']);
        if ($repository === null || $ref === null || $testSource === null) {
            return null;
        }

        return [
            'source' => self::blobUrl($repository, $ref, $managed['source']),
            'test' => self::blobUrl($repository, $ref, $testSource),
            'test_selector' => $managed['test'],
        ];
    }

    private static function repositoryUrl(string $url): ?string
    {
        if ($url === '' || strlen($url) > self::MAX_CONFIG_LENGTH
            || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'path'])) {
            return null;
        }
        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'github.com') {
            return null;
        }

        $path = rtrim($parts['path'] ?? '', '/');
        if (!preg_match('#\A/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z#D', $path)) {
            return null;
        }
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return 'https://github.com' . $path;
    }

    private static function repositoryRef(string $staticRef, string $refFile): ?string
    {
        if ($refFile !== '') {
            if (!self::isAbsoluteFilePath($refFile) || !is_file($refFile) || !is_readable($refFile)) {
                return null;
            }

            $contents = @file_get_contents($refFile, false, null, 0, self::MAX_REF_FILE_LENGTH);
            if (!is_string($contents) || strlen($contents) >= self::MAX_REF_FILE_LENGTH) {
                return null;
            }

            return self::validRef(rtrim($contents, "\r\n"));
        }

        return self::validRef($staticRef);
    }

    private static function isAbsoluteFilePath(string $path): bool
    {
        return $path !== ''
            && strlen($path) <= self::MAX_CONFIG_LENGTH
            && $path[0] === '/'
            && !preg_match('/[\x00-\x1f\x7f]/', $path);
    }

    private static function validRef(string $ref): ?string
    {
        return preg_match('/\A(?:master|[0-9a-f]{40})\z/D', $ref) === 1 ? $ref : null;
    }

    private static function blobUrl(string $repository, string $ref, string $path): string
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        return $repository . '/blob/' . rawurlencode($ref) . '/' . $encodedPath;
    }

    private static function legacyTestSelector(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        if (!preg_match('#/tests/suite/([a-z0-9][a-z0-9_./-]*)\.nix\z#D', $path, $matches)) {
            return null;
        }

        $selector = $matches[1] . '#*';
        return VpsAdminDocManagedPage::testSource($selector) !== null ? $selector : null;
    }
}
