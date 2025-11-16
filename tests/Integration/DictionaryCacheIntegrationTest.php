<?php

declare(strict_types=1);

namespace Alyakin\DictionaryCache\Tests\Integration;

use Alyakin\DictionaryCache\Adapters\PhpRedisClient;
use Alyakin\DictionaryCache\Services\DictionaryCacheService;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension redis
 */
final class DictionaryCacheIntegrationTest extends TestCase {
    private const CONTEXT = 'integration_suite';

    private \Redis $client;

    protected function setUp(): void {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is required for integration tests.');
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $this->client = new \Redis();
        $connected = @$this->client->connect($host, $port, 2.0);

        if (! $connected) {
            self::fail(sprintf('Unable to connect to Redis-compatible server at %s:%d', $host, $port));
        }

        $this->client->select(0);
        $this->client->flushDB();
    }

    protected function tearDown(): void {
        if (isset($this->client) && $this->client->isConnected()) {
            $this->client->flushDB();
            $this->client->close();
        }
    }

    public function testDictionaryWorkflowAgainstRealServer(): void {
        $service = new DictionaryCacheService(
            contextId: self::CONTEXT,
            dataProvider: static fn (): array => ['101', '202'],
            redisInstance: new PhpRedisClient($this->client)
        );

        $service->setTTL(30)->preload();

        $this->assertTrue($service->exists(), 'Cache should exist after preload');
        $this->assertTrue($service->hasItem('101'));

        $service->addItems(['303']);
        $this->assertTrue($service->hasItem('303'));

        $items = $service->getAllItems();
        sort($items);

        $this->assertSame(['101', '202', '303'], $items);

        $service->removeItem('202');
        $this->assertFalse($service->hasItem('202'));

        $service->clear();
        $this->assertFalse($service->exists());
    }
}
