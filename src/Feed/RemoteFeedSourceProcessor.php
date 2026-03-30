<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use Podquilt\Config\FeedSourceConfig;
use Podquilt\Http\FeedFetcherInterface;
use Podquilt\Logging\LoggerInterface;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\ClockInterface;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;

/**
 * Fetches, parses, filters, and optionally replays items from a remote RSS source.
 */
final readonly class RemoteFeedSourceProcessor
{
    public function __construct(
        private ClockInterface $clock,
        private UriFactory $uriFactory,
        private FeedFetcherInterface $feedFetcher,
    ) {
    }

    /**
     * @return list<FeedItem>
     */
    public function collectItems(
        FeedSourceConfig $source,
        LoggerInterface $logger,
        RequestContext $requestContext,
    ): array {
        $itemLimit = $source->effectiveItemLimit();

        if ($itemLimit <= 0) {
            return [];
        }

        $uri = $this->uriFactory->parseRemoteFeedUri($source->url);

        if ($uri === null) {
            $logger->log(LogLevel::Warning, 'Invalid URL for feed: ' . $source->url);

            return [];
        }

        $fetchResult = $this->feedFetcher->fetch($uri, $requestContext->userAgent);

        if ($fetchResult->transportError !== null) {
            $logger->log(LogLevel::Warning, 'Request for ' . $source->url . ' failed: ' . $fetchResult->transportError);

            return [];
        }

        if ($fetchResult->statusCode !== 200) {
            $logger->log(
                $fetchResult->statusCode < 400 ? LogLevel::Info : LogLevel::Warning,
                'Request for ' . $source->url . ' returned code ' . $fetchResult->statusCode . '.',
            );
        }

        if ($fetchResult->content === '') {
            return [];
        }

        $document = $this->parseDocument($fetchResult->content);
        $itemElements = $this->extractItemElements($document);

        $logger->log(LogLevel::Info, 'Beginning to parse feed: ' . $source->url);

        $replayPlan = $this->buildReplayPlan($source, $logger);
        $orderedElements = $replayPlan->enabled ? array_reverse($itemElements) : $itemElements;
        $items = [];
        $replayIndex = 0;

        foreach ($orderedElements as $itemElement) {
            if (count($items) >= $itemLimit) {
                break;
            }

            $item = FeedItem::fromDomElement($itemElement);

            if ($item === null) {
                continue;
            }

            $selection = $this->selectItem($item, $source, $replayPlan, $replayIndex);

            if ($selection->outcome === ItemSelectionOutcome::Include && $selection->item !== null) {
                $items[] = $selection->item;
            }
        }

        $logger->log(
            LogLevel::Info,
            sprintf('Parsing complete. %d items were retrieved from feed.', count($items)),
        );

        return $items;
    }

    private function selectItem(
        FeedItem $item,
        FeedSourceConfig $source,
        ReplayPlan $replayPlan,
        int &$replayIndex,
    ): ItemSelectionResult {
        if ($source->prepend !== null && $source->prepend !== '') {
            $item = $item->withPrependedTitle($source->prepend);
        }

        $item = $item->withHashedGuid();

        foreach ($source->filterPatterns as $nodeName => $pattern) {
            $fieldValue = $item->fieldValue($nodeName);

            if ($fieldValue !== null && preg_match('/' . $pattern . '/is', $fieldValue) === 0) {
                return ItemSelectionResult::exclude();
            }
        }

        if ($replayPlan->enabled) {
            if ($replayPlan->scheduledDates === [] || $replayPlan->originalStartDate === null) {
                return ItemSelectionResult::exclude();
            }

            if ($item->publishedAt < $replayPlan->originalStartDate) {
                return ItemSelectionResult::exclude();
            }

            if (!isset($replayPlan->scheduledDates[$replayIndex])) {
                return ItemSelectionResult::exclude();
            }

            $item = $item->withPublishedAt($replayPlan->scheduledDates[$replayIndex]);
            $replayIndex++;

            if ($replayPlan->remainingAfter($replayIndex) > $source->effectiveItemLimit()) {
                return ItemSelectionResult::exclude();
            }
        }

        $publicationCutoff = $this->now()->modify(sprintf('-%d days', $source->effectiveItemMaxAgeDays()));

        if ($item->publishedAt < $publicationCutoff || $item->publishedAt > $this->now()) {
            return ItemSelectionResult::exclude();
        }

        return ItemSelectionResult::include($item);
    }

    private function buildReplayPlan(FeedSourceConfig $source, LoggerInterface $logger): ReplayPlan
    {
        if ($source->replay === null) {
            return ReplayPlan::disabled();
        }

        if ($source->replay->schedule === null || !CronExpression::isValidExpression($source->replay->schedule)) {
            $logger->log(
                LogLevel::Error,
                "The feed's replay schedule is an improperly formatted cron expression.",
            );

            return ReplayPlan::disabled();
        }

        $replayStartDate = $this->parseDate($source->replay->replayStartDate);

        if ($replayStartDate === null) {
            $logger->log(
                LogLevel::Error,
                "The feed's replay start date is an improperly formatted timestamp.",
            );

            return ReplayPlan::disabled();
        }

        $originalStartDate = $this->parseDate($source->replay->originalStartDate);

        if ($originalStartDate === null) {
            $logger->log(
                LogLevel::Error,
                "The feed's replay original start date is an improperly formatted timestamp.",
            );

            return ReplayPlan::disabled();
        }

        $cron = new CronExpression($source->replay->schedule);
        $scheduledDates = [];
        $nextDate = $replayStartDate;

        while ($this->now() > $nextDate = DateTimeImmutable::createFromMutable($cron->getNextRunDate($nextDate))) {
            $scheduledDates[] = $nextDate;
        }

        return new ReplayPlan(
            enabled: true,
            scheduledDates: $scheduledDates,
            originalStartDate: $originalStartDate,
        );
    }

    private function parseDocument(string $content): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->recover = true;
        $document->strictErrorChecking = false;
        $document->preserveWhiteSpace = false;

        $normalized = $this->normalizeXmlPayload($content);

        libxml_use_internal_errors(true);
        $document->loadXML($normalized, LIBXML_PARSEHUGE | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();

        return $document;
    }

    /**
     * @return list<DOMElement>
     */
    private function extractItemElements(DOMDocument $document): array
    {
        $elements = [];

        foreach ($document->getElementsByTagName('item') as $itemNode) {
            $elements[] = $itemNode;
        }

        return $elements;
    }

    private function normalizeXmlPayload(string $content): string
    {
        foreach (['</rss>', '</xml>'] as $closingTag) {
            $offset = stripos($content, $closingTag);

            if ($offset !== false) {
                return substr($content, 0, $offset + strlen($closingTag));
            }
        }

        return $content;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $value, new DateTimeZone('UTC'));

        return $date ?: null;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
