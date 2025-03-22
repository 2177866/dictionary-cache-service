# Dictionary Cache Service

[![Latest Stable Version](https://img.shields.io/packagist/v/alyakin/dictionary-cache-service.svg?style=flat-square)](https://packagist.org/packages/alyakin/dictionary-cache-service)
[![Total Downloads](https://img.shields.io/packagist/dt/alyakin/dictionary-cache-service.svg?style=flat-square)](https://packagist.org/packages/alyakin/dictionary-cache-service)
![Laravel 8+](https://img.shields.io/badge/Laravel-8%2B-red.svg?style=flat-square)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg?style=flat-square)
![MIT License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)

Dictionary caching based on Redis-compatible stores (Redis, KeyDB, Valkey, Dragonfly, Ardb, etc.).

## 📌 Contents
- [Installation](#installation)
- [Methods](#methods)
  - [Creating an object](#__construct)
  - [Setup scope for cache](#setcontext)
  - [Set data provider](#setdataprovider)
  - [Set cache Time To Live (TTL)](#setttl)
  - [Get cache Time To Live (TTL)](#getttl)
  - [Mannualy preload cache](#preload)
  - [Check one item in cache](#hasitem)
  - [What items from the list are in the cache](#hasitems)
  - [Get all elements from cache](#getallitems)
  - [Checking is cache loaded](#exists)
  - [Mannualy add element in to cache](#additems)
  - [Mannualy remove element from the cache](#removeitem)
  - [Reset TTL countdoun](#keepalive)
  - [Clear Cache for the scope](#clear)
- [Supported Databases](#supported-databases)
- [Requirements](#requirements)
- [Contributing](#want-to-contribute)
- [License](#license)


## Installation

Install via Composer:

```sh
composer require alyakin/dictionary-cache-service
```

## Methods

### `__construct`
Initializes the service with optional context, data provider, and Redis connection.

**Parameters:**
  - `contextId` _(optional, string)_ → Unique identifier for the cache.
  - `dataProvider` _(optional, Closure)_ → Function that returns an array of items to be cached.
  - `redisInstance` _(optional, RedisConnection)_ → Custom Redis connection instance.

```php
$userCartCache = new \App\Services\DictionaryCacheService(
  contextId: $userId,
  dataProvider: $myDataProviderCallback
);
```

### `setContext`
Sets the cache key using a context ID and an optional prefix. All class methods use the scope set by this method.

**Parameters:**
  - `contextId` _(required, string)_ → Unique identifier for the context.
  - `key` _(optional, string)_ → Prefix for the cache key (default: `"dictionary"`).

```php
$userCartCache->setContext('user_'.$userId, 'cart');
$userFavoriteProductsCache->setContext('user_'.$userId, 'favorite_products');
```

### `setDataProvider`
Sets a function that provides data for cache preloading. This method will only be called if the cache has not been initialized yet.

**Parameters:**
  - `dataProvider` _(required, Closure)_ → Function returning an array of items.

```php
$userCartCache->setDataProvider(
  function () use ($userId) {
    return UserCart::whereUserId($userId)->pluck('id')->toArray();
  }
);
```

### `setTTL`
Sets the TTL (time-to-live) for the cache key.

**Parameters:**
  - `ttl` _(required, int)_ → TTL in seconds (must be >= 1).

``` php
$userCartCache = new \App\Services\DictionaryCacheService();
$userCartCache
  ->setContext('user_'.$userId, 'cart')
  ->setDataProvider(fn() => ['19', '33', '7'])
  ->setTTL(3600*24);
```

### `getTTL`
Retrieves the TTL of the cache key. If not set, returns default (3600).

### `preload`
Loads data into the cache using the data provider if it is not initialized.

### `hasItem`
Checks if a specific item exists in the cache.

**Parameters:**
  - `itemId` _(required, string)_ → Item to check.

``` php
$inCart = $userCartCache->hasItem($productId);
return $inCart;
```

### `hasItems`
Checks which items from the list exist in the cache.

**Parameters:**
  - `itemIds` _(required, array)_ → List of item IDs.

``` php
$productList = Product::recomendedFor($productID)->get()->pluck('id')->toArray();
$productsInCart = $userCartCache->hasItems($productList);
$recomendations = array_diff($productList, $productsInCart);
return $recomendations;
```

### `getAllItems`
Retrieves all cached items.

### `exists`
Checks if the cache exists for the scope.

### `addItems`
Adds multiple items to the cache.

```php
public function handle(ProductAddedToCart $event): void {
    $this->cartCache->setContext("user_{$event->userId}", 'cart');
    if ($this->cartCache->exists()) {
        $this->cartCache->addItems([$event->productId]);
    }
}
```

**Parameters:**
  - `items` _(required, array)_ → Items to add.

### `removeItem`
Removes a specific item from the cache.

**Parameters:**
  - `item` _(required, string)_ → Item to remove.

``` php
$this->cartCache->removeItem((string) $event->productId);
```

### `keepAlive`
Refreshes the expiration time of the cache key without modifying TTL.

```php
$this->cartCache->removeItem((string) $event->productId)->keepAlive();
```

### `clear`
Clears the cached data but keeps TTL settings.

## Supported Databases

The service works with **Redis-compatible** databases supported by Laravel's Redis driver:
- **Redis** (all versions)
- **KeyDB**
- **Valkey**
- **Dragonfly** _(tested with Redis-compatible API)_
- **Ardb**

## Requirements

The package requires:
- PHP **7.4+**
- Laravel **8+**
- Redis-compatible storage

## Want to Contribute?

Check out the [open issues](https://github.com/your-name/your-package/issues) — especially those labeled [good first issue](https://github.com/your-name/your-package/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22)!

Feel free to fork the repo, open a PR, or suggest improvements.

## License

This package is open-source and available under the **MIT License**.
