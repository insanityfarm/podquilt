<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use PHPUnit\Framework\TestCase;
use Podquilt\Http\SymfonyFeedFetcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Uri\Rfc3986\Uri;

final class SymfonyFeedFetcherTest extends TestCase
{
    public function testReturnsStatusAndContentWithoutThrowingOnHttpErrors(): void
    {
        $fetcher = new SymfonyFeedFetcher(new MockHttpClient([
            new MockResponse('<rss />', ['http_code' => 404]),
        ]));

        $result = $fetcher->fetch(new Uri('https://example.test/feed.xml'), 'PHPUnit');

        self::assertSame(404, $result->statusCode);
        self::assertSame('<rss />', $result->content);
        self::assertNull($result->transportError);
    }
}
