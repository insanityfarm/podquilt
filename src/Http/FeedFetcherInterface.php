<?php

declare(strict_types=1);

namespace Podquilt\Http;

/**
 * Abstracts remote feed retrieval so tests can replace the network with fixtures and orchestration can batch work.
 */
interface FeedFetcherInterface
{
    /**
     * Executes a batch of feed requests and returns the results keyed by request id.
     *
     * @param list<FeedFetchRequest> $requests
     * @return array<string, FetchResult>
     */
    public function fetchMany(array $requests, int $maxConcurrentRequests): array;
}
