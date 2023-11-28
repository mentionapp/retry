<?php

namespace Mention\Retry\Tests\Retry;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\RetryAsync;
use Mention\Retry\RetryStrategy\RetryStrategy;
use PHPUnit\Framework\TestCase;
use function React\Async\await;
use React\EventLoop\StreamSelectLoop;
use function React\Promise\reject;

class RetryAsyncTest extends TestCase
{
    public function testUnexpectedlyHighDelay(): void
    {
        if (Duration::maxDuration()->toSeconds() <= (PHP_INT_MAX / 1_000_000)) {
            self::markTestSkipped('The condition tested here can not happen');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('getDelay() is unexpectedly high and exceeds addTimer()\'s maximum interval');

        $retryStrategy = \Mockery::mock(RetryStrategy::class);
        $loop = \Mockery::mock(StreamSelectLoop::class);
        $retry = new RetryAsync($retryStrategy, $loop);

        $retryStrategy
            ->shouldReceive('getDelay')
            ->andReturn(Duration::maxDuration())
        ;

        $retryStrategy
            ->shouldReceive('shouldContinue')
            ->once()
            ->andReturn(true)
        ;

        await($retry->retry(function () {
            return reject(new \Exception());
        }));
    }
}
