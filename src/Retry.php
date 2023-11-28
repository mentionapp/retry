<?php

namespace Mention\Retry;

use Mention\Retry\RetryStrategy\RetryStrategy;
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use React\EventLoop\LoopInterface;

final class Retry
{
    private static ?RetryStrategy $overrideRetryStrategy = null;

    private static ?RetryStrategy $defaultRetryStrategy = null;

    /**
     * Create a synchronous retry instance.
     */
    public static function sync(RetryStrategy $retryStrategy = null): RetrySync
    {
        return new RetrySync(self::makeRetryStrategy($retryStrategy));
    }

    /**
     * Create an asynchronous retry instance.
     */
    public static function async(LoopInterface $loop, RetryStrategy $retryStrategy = null): RetryAsync
    {
        return new RetryAsync(self::makeRetryStrategy($retryStrategy), $loop);
    }

    /**
     * Override the retry strategy globally.
     *
     * When not null, all retry instances will use this retry strategy.
     *
     * This can be used in tests, and is discouraged in any other environment.
     */
    public static function overrideRetryStrategy(?RetryStrategy $retryStrategy): void
    {
        self::$overrideRetryStrategy = $retryStrategy;
    }

    /**
     * Set the default retry strategy.
     *
     * It can be useful to set a different default retry strategy in different
     * contexts. For example, code executing in a web context should retry
     * for a shorter time than code executing in a batch context. The default
     * retry strategy can be used for exactly that.
     */
    public static function setDefaultRetryStrategy(?RetryStrategy $retryStrategy): void
    {
        self::$defaultRetryStrategy = $retryStrategy;
    }

    public static function getDefaultRetryStrategy(): ?RetryStrategy
    {
        return self::$defaultRetryStrategy;
    }

    private static function makeRetryStrategy(?RetryStrategy $retryStrategy): RetryStrategy
    {
        if (null !== self::$overrideRetryStrategy) {
            return self::$overrideRetryStrategy;
        }

        if (null !== $retryStrategy) {
            return $retryStrategy;
        }

        if (null !== self::$defaultRetryStrategy) {
            return self::$defaultRetryStrategy;
        }

        return RetryStrategyBuilder::exponentialInteractive()->build();
    }
}
