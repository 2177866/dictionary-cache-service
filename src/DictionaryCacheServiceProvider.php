<?php

namespace Alyakin\DictionaryCache;

use Alyakin\DictionaryCache\Adapters\IlluminateRedisClient;
use Alyakin\DictionaryCache\Services\DictionaryCacheService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class DictionaryCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container.
     */
    public function register(): void
    {
        if (! is_object($this->app) || ! method_exists($this->app, 'bind')) {
            return;
        }

        $this->app->bind(DictionaryCacheService::class, function ($app) {
            return new DictionaryCacheService(
                redisInstance: new IlluminateRedisClient(Redis::connection())
            );
        });
    }

    /**
     * Perform post-registration booting.
     */
    public function boot(): void
    {
        //
    }
}
