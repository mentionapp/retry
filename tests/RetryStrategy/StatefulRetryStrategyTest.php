<?php

namespace Mention\Retry\Tests\Retry\RetryStrategy;

use Mention\Kebab\Clock\Clock;
use Mention\Kebab\Duration\Duration;
use Mention\Retry\DelayStrategy\ConstantDelay;
use Mention\Retry\DelayStrategy\DelayStrategyInterface;
use Mention\Retry\JitterStrategy\JitterStrategyInterface;
use Mention\Retry\JitterStrategy\NoJitter;
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use Mention\Retry\RetryStrategy\StatefulRetryStrategy;
use PHPUnit\Framework\TestCase;

class StatefulRetryStrategyTest extends TestCase
{
    public function testNoRetriesAfterMaxElaspedTime(): void
    {
        Clock::enableMocking();

        $retryStrategy = RetryStrategyBuilder
            ::constant()
            ->withMaxElapsedTime(Duration::milliSeconds(1010))
            ->withMaxFailures(null)
            ->build()
        ;

        $statefulStrategy = new StatefulRetryStrategy($retryStrategy);

        self::assertTrue($statefulStrategy->shouldContinue());
        self::assertGreaterThan(0, $statefulStrategy->getDelay()->toMilliSeconds());

        Clock::usleep(20 * 1000);
        $statefulStrategy->addFailure();

        self::assertFalse($statefulStrategy->shouldContinue());
    }

    public function testNoRetriesAfterMaxFailures(): void
    {
        Clock::enableMocking();

        $retryStrategy = RetryStrategyBuilder
            ::create(
                new ConstantDelay(),
                new NoJitter(),
            )
            ->withMaxFailures(2)
            ->build()
        ;

        $statefulStrategy = new StatefulRetryStrategy($retryStrategy);

        self::assertTrue($statefulStrategy->shouldContinue());
        self::assertGreaterThan(0, $statefulStrategy->getDelay()->toMilliSeconds());

        Clock::sleep(100);
        $statefulStrategy->addFailure();

        self::assertTrue($statefulStrategy->shouldContinue());
        self::assertGreaterThan(0, $statefulStrategy->getDelay()->toMilliSeconds());

        Clock::sleep(100);
        $statefulStrategy->addFailure();

        self::assertFalse($statefulStrategy->shouldContinue());
    }

    public function testDelayIsClampedToMaxDelay(): void
    {
        $retryStrategy = RetryStrategyBuilder
            ::constant()
            ->withMaxFailures(2)
            ->withMaxDelay(Duration::milliSeconds(100))
            ->build()
        ;

        $statefulStrategy = new StatefulRetryStrategy($retryStrategy);

        self::assertTrue($statefulStrategy->shouldContinue());

        $statefulStrategy->addFailure();

        self::assertSame(100, $statefulStrategy->getDelay()->toMilliSecondsInt());
        self::assertSame(1, $statefulStrategy->getFailures());
    }

    public function testDelayAndJitterStrategiesAreUsed(): void
    {
        $delayStrategy = \Mockery::mock(DelayStrategyInterface::class);

        $delayStrategy
            ->shouldReceive('getDelay')
            ->once()
            ->with(1)
            ->andReturn(Duration::milliSeconds(10))
        ;

        $delayStrategy
            ->shouldReceive('getDelay')
            ->once()
            ->with(2)
            ->andReturn(Duration::milliSeconds(20))
        ;

        $jitterStrategy = \Mockery::mock(JitterStrategyInterface::class);

        $jitterStrategy
            ->shouldReceive('apply')
            ->twice()
            ->andReturnUsing(function (Duration $delay) {
                return $delay->add(Duration::milliSeconds(1));
            })
        ;

        $retryStrategy = RetryStrategyBuilder::create($delayStrategy, $jitterStrategy)
            ->withMaxFailures(2)
            ->build()
        ;

        $statefulStrategy = new StatefulRetryStrategy($retryStrategy);

        self::assertTrue($statefulStrategy->shouldContinue());
        self::assertSame(11, $statefulStrategy->addFailure()->getDelay()->toMilliSecondsInt());

        self::assertTrue($statefulStrategy->shouldContinue());
        self::assertSame(21, $statefulStrategy->addFailure()->getDelay()->toMilliSecondsInt());
    }
}
