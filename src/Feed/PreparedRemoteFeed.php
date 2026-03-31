<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use Podquilt\Config\FeedSourceConfig;
use Podquilt\Http\FeedFetchRequest;
use Podquilt\Http\FetchResult;
use Podquilt\Logging\BufferedLogger;
use Podquilt\Logging\LoggerInterface;

/**
 * Carries the remote feed source together with any validated request Podquilt should execute for it.
 */
final readonly class PreparedRemoteFeed
{
    public function __construct(
        public FeedSourceConfig $source,
        public ?FeedFetchRequest $request,
        private BufferedLogger $logger,
    ) {
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Resolves the batch fetch result for this feed and returns a synthetic transport failure when
     * the fetcher omits a requested response entirely.
     *
     * @param array<string, FetchResult> $fetchResults
     */
    public function resolveFetchResult(array $fetchResults): ?FetchResult
    {
        if ($this->request === null) {
            return null;
        }

        return $fetchResults[$this->request->id] ?? new FetchResult(
            statusCode: 0,
            content: '',
            transportError: 'The HTTP fetcher did not return a result for ' . $this->request->uri->toString() . '.',
        );
    }

    public function flushLogsTo(LoggerInterface $logger): void
    {
        $this->logger->flushTo($logger);
    }
}
