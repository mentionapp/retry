<?php

namespace Mention\Retry\Tests\Retry;

use Exception;
use Mention\Retry\PermanentError;
use Mention\Retry\Retry;
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use PHPUnit\Framework\TestCase;
use function React\Async\await;
use React\EventLoop\StreamSelectLoop;
use function React\Promise\reject;
use function React\Promise\resolve;

class RetryTest extends TestCase
{
    protected function setUp(): void
    {
        Retry::overrideRetryStrategy(null);
    }

    public function testFailuresAndRecovery(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            $called++;
            if ($called <= 2) {
                return reject(new \Exception((string) $called));
            }

            return resolve("ok: {$called}");
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::assertSame('ok: 3', await($promise));
    }

    public function testHardFailures(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            $called++;

            return reject(new \Exception("ko: {$called}"));
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::expectExceptionMessage('ko: 10');
        await($promise);
    }

    public function testFailuresAndRecoveryWithLoop(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            $called++;
            if ($called <= 2) {
                return reject(new \Exception((string) $called));
            }

            return resolve("ok: {$called}");
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::assertSame('ok: 3', await($promise));
    }

    public function testHardFailuresWithLoop(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            return reject(new \Exception((string) $called));
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::expectException(\Exception::class);
        self::expectExceptionMessage('0');
        await($promise);
    }

    public function testPermanentErrorWithLoop(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Some permanent error');

        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            $called++;
            self::assertLessThanOrEqual(1, $called);

            return reject(PermanentError::withException(
                new \Exception('Some permanent error'),
            ));
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::expectExceptionMessage('Some permanent error');
        await($promise);
    }

    public function testSuccessRetry(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        self::assertTrue(Retry::sync($b)->retry(function () {
            return true;
        }));
    }

    public function testLastExceptionRetry(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $this->expectException('Exception');

        Retry::sync($b)->retry(function () {
            throw new Exception();
        });
    }

    public function testRecoverUntilSuccess(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $retry = Retry::sync($b);
        self::assertTrue($retry->retry(function () use ($retry) {
            if (5 !== $retry->getFailures()) {
                throw new Exception();
            }

            return true;
        }));
        self::assertEquals(5, $retry->getFailures());
    }

    public function testNotifyCallback(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $retry = Retry::sync($b);
        $called = 0;
        self::assertTrue($retry->retry(function () use ($retry) {
            if (5 !== $retry->getFailures()) {
                throw new Exception();
            }

            return true;
        }, function (Exception $exception, $wait) use (&$called) {
            $called++;
        }));

        self::assertEquals(5, $retry->getFailures());
        self::assertEquals(5, $called);
    }

    public function testPermanentError(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Some permanent error');

        $b = RetryStrategyBuilder::immediate()->build();

        $retry = Retry::sync($b);

        $called = 0;
        $retry->retry(function () use (&$called) {
            $called++;
            self::assertLessThanOrEqual(1, $called);

            throw PermanentError::withException(new \Exception('Some permanent error'));
        });
    }

    public function testThrownPermanentErrorWithLoop(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            $called++;
            self::assertLessThanOrEqual(1, $called);

            if ($called < PHP_INT_MAX) {
                throw PermanentError::withException(
                    new \InvalidArgumentException('Some permanent error'),
                );
            }

            return resolve(null);
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Some permanent error');
        await($promise);
    }

    public function testThrownExceptionWithLoop(): void
    {
        $b = RetryStrategyBuilder::immediate()->build();

        $loop = new StreamSelectLoop();

        $r = Retry::async($loop, $b);

        $called = 0;
        $cb = function () use (&$called) {
            if ($called === 0) {
                $called++;

                throw new \InvalidArgumentException('Some permanent error');
            }

            return resolve('ok');
        };

        $promise = $r->retry($cb);

        $loop->run();

        self::assertSame('ok', await($promise));
    }

    public function testDefaultRetryStrategyIsNotMutated(): void
    {
        $retryStrategy = RetryStrategyBuilder::immediate()->build();

        try {
            Retry::setDefaultRetryStrategy($retryStrategy);

            $retry = Retry::sync();

            try {
                await($retry->retry(function () {
                    return reject(new \Exception());
                }));
            } catch (\Exception $e) {
            }

            self::assertSame(0, $retry->getFailures());
        } finally {
            Retry::setDefaultRetryStrategy(null);
        }
    }
}
