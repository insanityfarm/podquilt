<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use PHPUnit\Framework\TestCase;
use Podquilt\Config\ConfigException;
use Podquilt\Config\ConfigLoader;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\RequestContext;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsDefaultsAndPreservesKnownKeysOnly(): void
    {
        $path = $this->writeTempConfig([
            'channel' => [
                'title' => 'Custom Feed',
                'unexpected' => 'ignored',
            ],
            'logs' => [
                'level' => 4,
            ],
        ]);

        $config = (new ConfigLoader())->load($path, $this->requestContext());

        self::assertSame('Custom Feed', $config->channel->title);
        self::assertSame('example.test/feed', $config->channel->link);
        self::assertSame('Your description here.', $config->channel->description);
        self::assertSame([], $config->feeds);
        self::assertSame([], $config->files);
        self::assertTrue($config->logs->enabled);
        self::assertSame(LogLevel::Info, $config->logs->level);
        self::assertSame('logs/podquilt.log', $config->logs->path);
    }

    public function testThrowsHelpfulExceptionForMissingConfig(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unable to find config.json. Please create a configuration file and try again.');

        (new ConfigLoader())->load('/tmp/definitely-missing-podquilt-config.json', $this->requestContext());
    }

    public function testThrowsHelpfulExceptionForInvalidJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'podquilt-config-');
        file_put_contents($path, '{invalid json');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unable to read config.json. Please check that is is properly formatted and try again.');

        (new ConfigLoader())->load($path, $this->requestContext());
    }

    private function requestContext(): RequestContext
    {
        return new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeTempConfig(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'podquilt-config-');
        file_put_contents($path, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }
}
