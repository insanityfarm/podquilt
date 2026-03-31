<?php

declare(strict_types=1);

namespace Podquilt\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Uses Symfony's HTTP client to fetch feeds with explicit timeouts, redirect handling, and bounded concurrency.
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
    public function fetchMany(array $requests, int $maxConcurrentRequests): array
    {
        if ($requests === []) {
            return [];
        }

        $pendingRequests = $requests;
        $results = [];
        $activeResponses = [];
        $nextPendingIndex = 0;
        $concurrencyLimit = max(1, $maxConcurrentRequests);

        $fillActivePool = function () use (
            &$activeResponses,
            $concurrencyLimit,
            &$nextPendingIndex,
            $pendingRequests,
            &$results,
        ): void {
            while (count($activeResponses) < $concurrencyLimit && isset($pendingRequests[$nextPendingIndex])) {
                $request = $pendingRequests[$nextPendingIndex];
                $nextPendingIndex++;

                try {
                    $response = $this->httpClient->request('GET', $request->uri->toString(), $this->requestOptions($request));
                    $activeResponses[spl_object_id($response)] = [
                        'request' => $request,
                        'response' => $response,
                    ];
                } catch (TransportExceptionInterface $exception) {
                    $results[$request->id] = new FetchResult(
                        statusCode: 0,
                        content: '',
                        transportError: $exception->getMessage(),
                    );
                }
            }
        };

        $fillActivePool();

        while ($activeResponses !== []) {
            $completionHandled = false;

            foreach ($this->httpClient->stream($this->responsesFromActivePool($activeResponses)) as $response => $chunk) {
                if ($chunk->isFirst()) {
                    // Symfony's streaming transport enforces status-code checking after the first yielded chunk
                    // unless callers have already consumed the headers. Podquilt treats non-200 responses as
                    // regular fetch results, so we mark the headers as consumed without throwing here.
                    $response->getHeaders(false);

                    continue;
                }

                if (!$chunk->isLast()) {
                    continue;
                }

                $activeEntryKey = spl_object_id($response);
                $request = $activeResponses[$activeEntryKey]['request'];
                unset($activeResponses[$activeEntryKey]);

                $results[$request->id] = $this->finalizeResponse($response);
                $fillActivePool();
                $completionHandled = true;

                // Restart the stream with the refreshed active pool so newly launched requests are polled immediately.
                break;
            }

            if (!$completionHandled) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param array<int, array{request: FeedFetchRequest, response: ResponseInterface}> $activeResponses
     * @return list<ResponseInterface>
     */
    private function responsesFromActivePool(array $activeResponses): array
    {
        return array_values(array_map(
            static fn (array $activeEntry): ResponseInterface => $activeEntry['response'],
            $activeResponses,
        ));
    }

    private function finalizeResponse(ResponseInterface $response): FetchResult
    {
        try {
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

    /**
     * @return array{headers: array{Accept: string, User-Agent: string}}
     */
    private function requestOptions(FeedFetchRequest $request): array
    {
        return [
            'headers' => [
                'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                'User-Agent' => $request->userAgent,
            ],
        ];
    }
}
