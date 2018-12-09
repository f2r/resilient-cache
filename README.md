# Resilient Cache

This cache system combines retrieval and storage in a single call, with data retrieval through a callback.
When data expires and the callback function throw an exception, an outdated version of the original data is returned.

This is very useful when caching a request to an external API.

```php
<?php
// if error, return stale data for 5 seconds. Keep stale data for 3600 seconds.
$cache = new \f2r\ResilientCache\ResilientCache(new Cache(), 3600, 5);

$url = 'http://my-api/get/resource';

$cache->that(function() use ($url) {
    $value = file_get_contents($url);
    if ($value === null) {
        throw new \Exception('Could not retrive data from api');
    }
    return $value;
}, md5($url), 300); // Data TTL: 300 seconds
```

## Retry TTL

"Retry TTL" (third constructor parameter) is useful in case of data retrieve unavailablity.
In this case, all cache requests will fail. With a short retry TTL, it will delay each request for a few seconds.

## Tests

Launch tests using "phpunit" like this:
```shell
composer install --dev
vendor/bin/phpunit tests/
```