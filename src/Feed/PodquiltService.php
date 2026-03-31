<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use Podquilt\Config\AppConfig;
use Podquilt\Http\FeedFetcherInterface;
use Podquilt\Logging\LoggerInterface;
use Podquilt\Runtime\RequestContext;

/**
 * Orchestrates source preparation, bounded remote fetching, and final RSS rendering for a request.
 */
final readonly class PodquiltService
{
    public function __construct(
        private RemoteFeedSourceProcessor $remoteProcessor,
        private FileFeedSourceProcessor $fileProcessor,
        private FeedFetcherInterface $feedFetcher,
        private RssRenderer $renderer,
    ) {
    }

    public function render(
        AppConfig $config,
        RequestContext $requestContext,
        LoggerInterface $logger,
    ): string {
        $items = [];
        $preparedRemoteFeeds = [];
        $remoteRequests = [];

        foreach ($config->feeds as $index => $source) {
            if (!$source->isEnabled()) {
                continue;
            }

            $preparedFeed = $this->remoteProcessor->prepareFeed($index, $source, $requestContext);
            $preparedRemoteFeeds[] = $preparedFeed;

            if ($preparedFeed->request !== null) {
                $remoteRequests[] = $preparedFeed->request;
            }
        }

        $fetchResults = $this->feedFetcher->fetchMany($remoteRequests, $config->http->maxConcurrentRequests);

        foreach ($preparedRemoteFeeds as $preparedFeed) {
            $fetchResult = $preparedFeed->resolveFetchResult($fetchResults);

            if ($fetchResult !== null) {
                $this->appendItems(
                    $items,
                    $this->remoteProcessor->collectItemsFromFetchResult(
                        $preparedFeed,
                        $fetchResult,
                    ),
                );
            }

            $preparedFeed->flushLogsTo($logger);
        }

        foreach ($config->files as $source) {
            if (!$source->isEnabled()) {
                continue;
            }

            $this->appendItems($items, $this->fileProcessor->collectItems($source, $logger));
        }

        usort(
            $items,
            static fn (FeedItem $left, FeedItem $right): int => $right->publishedAt <=> $left->publishedAt,
        );

        return $this->renderer->render($config->channel, $items);
    }

    /**
     * @param list<FeedItem> $items
     * @param list<FeedItem> $itemsToAppend
     */
    private function appendItems(array &$items, array $itemsToAppend): void
    {
        foreach ($itemsToAppend as $item) {
            $items[] = $item;
        }
    }
}
