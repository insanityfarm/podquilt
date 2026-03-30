<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

use Podquilt\Http\FeedFetcherInterface;
use Podquilt\Http\FetchResult;
use Uri\Rfc3986\Uri;

/**
 * Maps URLs to canned responses so feed-processing tests stay hermetic.
 */
final readonly class FixtureFeedFetcher implements FeedFetcherInterface
{
    /**
     * @param array<string, FetchResult> $responses
     */
    public function __construct(
        private array $responses,
    ) {
    }

    #[\Override]
    public function fetch(Uri $uri, string $userAgent): FetchResult
    {
        return $this->responses[$uri->toString()] ?? new FetchResult(404, '');
    }
}
