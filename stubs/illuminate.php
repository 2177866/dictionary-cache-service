<?php

namespace Illuminate\Support {
    class ServiceProvider
    {
        /** @var object|null */
        public $app;

        public function __construct($app = null)
        {
            $this->app = $app;
        }

        public function register(): void {}

        public function boot(): void {}
    }
}

namespace Illuminate\Support\Facades {
    class Redis
    {
        public static function connection()
        {
            return new \Illuminate\Redis\Connections\Connection;
        }
    }
}

namespace Illuminate\Redis\Connections {
    class Connection
    {
        public function exists(string $key): bool
        {
            return true;
        }

        public function sadd(string $key, string ...$members): void {}

        /** @return string[] */
        public function smembers(string $key): array
        {
            return [];
        }

        public function sismember(string $key, string $member): bool
        {
            return true;
        }

        /** @return string[] */
        public function sinter(string $key, string $otherKey): array
        {
            return [];
        }

        public function srem(string $key, string $member): void {}

        public function expire(string $key, int $ttl): void {}

        public function set(string $key, int|string $value): void {}

        public function get(string $key): ?string
        {
            return null;
        }

        public function del(string ...$keys): void {}
    }
}
