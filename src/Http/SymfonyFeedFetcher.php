<?php

declare(strict_types=1);

namespace Podquilt\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Uri\Rfc3986\Uri;

/**
 * Uses Symfony's HTTP client to fetch feeds with explicit timeouts and redirect handling.
 */
final readonly class SymfonyFeedFetcher implements FeedFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(HttpClient::create([
            'max_redirects' => 10,
            'timeout' => 15.0,
            'max_duration' => 20.0,
        ]));
    }

    #[\Override]
    public function fetch(Uri $uri, string $userAgent): FetchResult
    {
        try {
            $response = $this->httpClient->request('GET', $uri->toString(), [
                'headers' => [
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                    'User-Agent' => $userAgent,
                ],
            ]);

            return new FetchResult(
                statusCode: $response->getStatusCode(),
                content: $response->getContent(false),
            );
        } catch (TransportExceptionInterface $exception) {
            return new FetchResult(
                statusCode: 0,
                content: '',
                transportError: $exception->getMessage(),
            );
        }
    }
}
