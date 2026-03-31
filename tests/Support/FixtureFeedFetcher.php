<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

use Podquilt\Http\FeedFetcherInterface;
use Podquilt\Http\FetchResult;

/**
 * Maps URLs to canned responses so feed-processing tests stay hermetic and deterministic.
 */
final class FixtureFeedFetcher implements FeedFetcherInterface
{
    private ?int $lastMaxConcurrentRequests = null;

    /**
     * @param array<string, FetchResult> $responses
     * @param list<string>|null $resultUrlOrder
     * @param list<string> $omittedResultUrls
     */
    public function __construct(
        private array $responses,
        private ?array $resultUrlOrder = null,
        private array $omittedResultUrls = [],
    ) {
    }

    #[\Override]
    public function fetchMany(array $requests, int $maxConcurrentRequests): array
    {
        $this->lastMaxConcurrentRequests = $maxConcurrentRequests;
        $resultsByUrl = [];

        foreach ($requests as $request) {
            $resultsByUrl[$request->uri->toString()] = [
                'id' => $request->id,
                'result' => $this->responses[$request->uri->toString()] ?? new FetchResult(404, ''),
            ];
        }

        $orderedUrls = $this->resultUrlOrder ?? array_keys($resultsByUrl);
        $results = [];

        foreach ($orderedUrls as $url) {
            if (!array_key_exists($url, $resultsByUrl) || in_array($url, $this->omittedResultUrls, true)) {
                continue;
            }

            $results[$resultsByUrl[$url]['id']] = $resultsByUrl[$url]['result'];
        }

        foreach ($resultsByUrl as $url => $resultByUrl) {
            if (in_array($url, $this->omittedResultUrls, true)) {
                continue;
            }

            if (!array_key_exists($resultByUrl['id'], $results)) {
                $results[$resultByUrl['id']] = $resultByUrl['result'];
            }
        }

        return $results;
    }

    public function lastMaxConcurrentRequests(): ?int
    {
        return $this->lastMaxConcurrentRequests;
    }
}
