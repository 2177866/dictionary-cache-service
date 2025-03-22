<?php

namespace Alyakin\DictionaryCache;

use Illuminate\Support\ServiceProvider;
use Alyakin\DictionaryCache\Service\DictionaryCacheService;

class DictionaryCacheServiceProvider extends ServiceProvider {
    /**
     * Register services in the container.
     */
    public function register(): void {
        $this->app->bind(DictionaryCacheService::class, function ($app) {
            return new DictionaryCacheService();
        });
    }

    /**
     * Perform post-registration booting.
     */
    public function boot(): void {
        //
    }
}
