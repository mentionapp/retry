<?php

namespace Mention\Retry;

use Exception;
use Mention\Kebab\Duration\Duration;
use Mention\Retry\RetryStrategy\RetryStrategy;
use Mention\Retry\RetryStrategy\StatefulRetryStrategy;
use React\EventLoop\LoopInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

class RetryAsync
{
    private readonly StatefulRetryStrategy $retryStrategy;

    /**
     * Use Retry::async.
     */
    public function __construct(
        RetryStrategy $retryStrategy,
        private readonly LoopInterface $loop,
    ) {
        $this->retryStrategy = new StatefulRetryStrategy($retryStrategy);
    }

    /**
     * Calls $function repeatedly until it succeeds or an exit condition occurs.
     *
     * The function is called repeatedly until one of the following conditions
     * is met:
     *
     * - It returned a promise that resolved successfully
     * - It returned a promise that got rejected with a PermanentError instance
     * - The RetryStrategy decided that it should not continue
     *
     * Each retry is made after a delay according to the retry strategy.
     *
     * The $notify function is called before the delay. It takes an exception
     * and the interval to the next call as parameters.
     *
     * @template T
     *
     * @param callable():PromiseInterface<T>                             $function
     * @param null|callable(mixed $exception, Duration $nextDelay): void $notify
     *
     * @return PromiseInterface<T>
     */
    public function retry(callable $function, callable $notify = null): PromiseInterface
    {
        try {
            $promise = $function();
        } catch (Exception $e) {
            return $this->errorHandler($e, $function, $notify);
        }

        return $promise->catch(function ($err) use ($function, $notify) {
            return $this->errorHandler($err, $function, $notify);
        });
    }

    /**
     * @template T
     *
     * @param callable():PromiseInterface<T>                             $function
     * @param null|callable(mixed $exception, Duration $nextDelay): void $notify
     *
     * @return PromiseInterface<T>
     */
    private function errorHandler(\Throwable $err, callable $function, ?callable $notify): PromiseInterface
    {
        $this->retryStrategy->addFailure();

        if ($err instanceof PermanentError) {
            return reject($err->getPrevious() ?? $err);
        }

        if (!$this->retryStrategy->shouldContinue()) {
            return reject($err);
        }

        $delay = $this->retryStrategy->getDelay();
        if ($delay->toSeconds() > (PHP_INT_MAX / 1_000_000)) {
            throw new \RuntimeException(sprintf(
                "getDelay() is unexpectedly high and exceeds addTimer()'s maximum interval: %f seconds",
                $delay->toSeconds(),
            ));
        }

        if (null !== $notify) {
            $notify($err, $delay);
        }

        return new Promise\Promise(function ($resolve) use ($function, $notify, $delay) {
            $this->loop->addTimer($delay->toSeconds(), function () use ($resolve, $function, $notify) {
                $resolve($this->retry($function, $notify));
            });
        });
    }
}
