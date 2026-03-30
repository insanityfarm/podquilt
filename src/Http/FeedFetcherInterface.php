<?php

declare(strict_types=1);

namespace Podquilt\Http;

use Uri\Rfc3986\Uri;

/**
 * Abstracts remote feed retrieval so tests can replace the network with fixtures.
 */
interface FeedFetcherInterface
{
    public function fetch(Uri $uri, string $userAgent): FetchResult;
}
