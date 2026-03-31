<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use PHPUnit\Framework\TestCase;
use Podquilt\Http\FeedFetchRequest;
use Podquilt\Http\SymfonyFeedFetcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Uri\Rfc3986\Uri;

final class SymfonyFeedFetcherTest extends TestCase
{
    public function testReturnsStatusAndContentWithoutThrowingOnHttpErrors(): void
    {
        $fetcher = new SymfonyFeedFetcher(new MockHttpClient([
            new MockResponse(
                (static function (): iterable {
                    yield '<rss />';
                })(),
                ['http_code' => 404],
            ),
        ]));

        $result = $fetcher->fetchMany([
            new FeedFetchRequest('feed-1', new Uri('https://example.test/feed.xml'), 'PHPUnit'),
        ], 1);

        self::assertSame(404, $result['feed-1']->statusCode);
        self::assertSame('<rss />', $result['feed-1']->content);
        self::assertNull($result['feed-1']->transportError);
    }

    public function testHonorsConfiguredConcurrencyLimitWhenFetchingBatch(): void
    {
        $activeRequests = 0;
        $maxActiveRequests = 0;
        $fetcher = new SymfonyFeedFetcher(new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$activeRequests, &$maxActiveRequests): MockResponse {
                $activeRequests++;
                $maxActiveRequests = max($maxActiveRequests, $activeRequests);

                return new MockResponse(
                    (static function () use (&$activeRequests, $url): iterable {
                        yield $url;
                        $activeRequests--;
                    })(),
                    ['http_code' => 200],
                );
            },
        ));

        $results = $fetcher->fetchMany([
            new FeedFetchRequest('feed-1', new Uri('https://example.test/one.xml'), 'PHPUnit'),
            new FeedFetchRequest('feed-2', new Uri('https://example.test/two.xml'), 'PHPUnit'),
            new FeedFetchRequest('feed-3', new Uri('https://example.test/three.xml'), 'PHPUnit'),
        ], 2);

        self::assertSame(2, $maxActiveRequests);
        self::assertSame('https://example.test/one.xml', $results['feed-1']->content);
        self::assertSame('https://example.test/two.xml', $results['feed-2']->content);
        self::assertSame('https://example.test/three.xml', $results['feed-3']->content);
    }
}
