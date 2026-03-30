<?php

declare(strict_types=1);

namespace Podquilt\Runtime;

use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

/**
 * Centralizes URI parsing so the app can rely on the PHP 8.5 URI extension instead of ad hoc filters.
 */
final readonly class UriFactory
{
    /**
     * Parses a general absolute URI or returns null when the value is unusable.
     */
    public function parseAbsoluteUri(string $value): ?Uri
    {
        try {
            $uri = new Uri(trim($value));
        } catch (InvalidUriException) {
            return null;
        }

        if ($uri->getScheme() === '') {
            return null;
        }

        if ($uri->getScheme() !== 'file' && $uri->getHost() === '') {
            return null;
        }

        return $uri;
    }

    /**
     * Parses a remote feed URL and enforces an HTTP(S) transport that the fetcher can retrieve.
     */
    public function parseRemoteFeedUri(string $value): ?Uri
    {
        $uri = $this->parseAbsoluteUri($value);

        if ($uri === null) {
            return null;
        }

        return in_array($uri->getScheme(), ['http', 'https'], true) ? $uri : null;
    }
}
