# Retry

Retry is a PHP library for retrying operations with customizable backoff, jitter, and support for both synchronous and asynchronous APIs. It also allows for offline retrying and serialization.

## Features

- Support for both synchronous and asynchronous operations
- Configurable delays: constant or exponential
- Jitter to prevent synchronization of retry attempts
- Halting on irrecoverable errors
- Offline retry capability
- Explicit serialization support

## Synchronous API

``` php
use Mention\Retry\Retry;
use GuzzleHttp\Client;

$result = Retry::sync()->retry($operation);
```

`$operation` should be a callable, and will be called repeatedly until either:

- The function executes successfully (returns without exceptions),
- A PermanentError is thrown indicating a retry would have the same outcome,
- The RetryStrategy halted the retrying process after a certain number of retries or elapsed time

On success, `Retry::sync()->retry($operation)` returns the value of `$operation()`.

The following example will retry until `$client->get('http://example.com/')` is successful, or 1 minute has elasped (as per the default RetryStrategy) :

``` php
use Mention\Retry\Retry;
use GuzzleHttp\Client;

$response = Retry::sync()->retry(function() {
    $client = new Guzzlehttp\Client();
    return $client->get('http://example.com/');
});
```

## Asynchronous API

The async API works similarly, but expects the operation to return a `React\PromiseInterface`:

``` php
use Mention\Retry\Retry;

Retry::async($loop)->retry(function() {
    $browser = new React\Http\Browser();
    return $browser->get('http://example.com/');
});
```

## Permanent errors

Sometimes it can be determined that retrying will not work. For example, it is unlikely that retrying after receiving an HTTP 4xx error will change the outcome.

In these cases the operation can halt the retry process by throwing a `Mention\Retry\PermanentError`. When this exceptions is thrown, `retry()` stops retrying and re-throws either the inner exception if any, or the PermanentError itself.

Example:

``` php
use Mention\Retry\Retry;
use Mention\Retry\PermanentError;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

$response = Retry::sync()->retry(function() {
    $client = new Guzzlehttp\Client();
    try {
        return $client->get('http://example.com/404');
    } catch (ClientException $e) {
        throw PermanentError::withException($e);
    }
});
```

## RetryStrategy

Both `Retry::sync()` and `Retry::async()` accept a `RetryStrategy` argument that can be used to configure the retry behavior.

``` php
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use Mention\Kebab\Duration\Duration;

$strategy = RetryStrategyBuilder::exponentialInteractive()->withMaxElapsedTime(Duration::seconds(15));
$response = Retry::sync($strategy)->retry($operation);
```

### Default strategy

When no `RetryStrategy` is passed, a default strategy is used. It is configured as follows:

- The delay is exponential, starts at 10 milliseconds and increases by a factor of 1.5 after each attempt
- A jitter of 0.5 is applied to the delay to help prevent operations from synchronising with each other and sending surges of requests
- Retrying halts after 60 seconds have elasped without success

It is possible to change the default strategy by calling `Mention\Retry::setDefaultRetryStrategy()`:

``` php
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use Mention\Kebab\Duration\Duration;

$defaultStrategy = RetryStrategyBuilder::exponentialInteractive()->withMaxElapsedTime(Duration::seconds(15));
Retry::setDefaultRetryStrategy($defaultStrategy);
```

It can be useful to set a different default retry strategy in different contexts. For example, code executing in a web context should retry for a shorter time than code executing in a batch context. Changing the default retry strategy can be used for exactly that.

In tests it can be useful to ignore the default and custom strategies. This can be done by calling  `Mention\Retry::overrideRetryStrategy()`:

``` php
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;

$testStrategy = RetryStrategyBuilder::noDelay();
Retry::overrideRetryStrategy($defaultStrategy);
```

Use of `Retry::overrideRetryStrategy()` outside of tests is discouraged. Use `Retry::setDefaultRetryStrategy()` instead, or pass an explicit strategy when calling `Retry::sync()` and `Retry::async()`.

### Custom strategies

The `Mention\Retry\RetryStrategy\RetryStrategyBuilder` class allows you to build custom strategies and to customize the following parameters:

- The delay between two attempts
- How much jitter should be applied to the delay
- The maximum number of attempts
- The maximum amount of elapsed time

The builder provides a number of predefine strategies that can be further customized. See https://github.com/mentionapp/retry/blob/main/src/RetryStrategy/RetryStrategyBuilder.php.

### Serialization

Retry strategies can be serialized by calling the `->toJsonString()` method and deserialized by calling `::fromJsonString()`. This is designed to be persisted for later usage, and future versions of the library will support strategies serialized with previous versions.

## Offline retrying

`RetryStrategy` is stateless and can be used standalone to implement offline retrying. For example, it can be used in a scheduled task system in order to decide if and when to re-schedule a failing task:

``` php
try {
    $task->execute();
} catch (\Exception $e) {
    $task->increaseFailures();
    $retryStrategy = $task->getRetryStrategy();
    if (!$retryStrategy->shouldRetry($task->getFailuesCount(), $task->getOriginalScheduleTime())) {
        throw $e;
    } else {
        $delay = $retryStrategy->getDelay($task->getFailuesCount());
        $task->rescheduleWithDelay($delay);
    }
}
```
