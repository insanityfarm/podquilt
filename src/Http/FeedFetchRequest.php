<?php

declare(strict_types=1);

namespace Podquilt\Http;

use Uri\Rfc3986\Uri;

/**
 * Describes one validated remote feed request that can be executed by the batch fetcher.
 */
final readonly class FeedFetchRequest
{
    public function __construct(
        public string $id,
        public Uri $uri,
        public string $userAgent,
    ) {
    }
}
