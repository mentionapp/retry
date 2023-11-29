<?php

namespace Mention\Retry\Tests\Retry\RetryStrategy;

use Mention\Kebab\Clock\Clock;
use Mention\Kebab\Duration\Duration;
use Mention\Retry\DelayStrategy\ConstantDelay;
use Mention\Retry\DelayStrategy\DelayStrategyInterface;
use Mention\Retry\JitterStrategy\JitterStrategyInterface;
use Mention\Retry\JitterStrategy\NoJitter;
use Mention\Retry\RetryStrategy\RetryStrategy;
use Mention\Retry\RetryStrategy\RetryStrategyBuilder;
use PHPUnit\Framework\TestCase;

class RetryStrategyTest extends TestCase
{
    public function testRetryStrategyValidatesOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RetryStrategy must have a max elapsed time or max failures');

        new RetryStrategy(
            new ConstantDelay(),
            new NoJitter(),
            null,
            null,
            Duration::milliSeconds(10),
        );
    }

    public function testNoRetriesAfterMaxElaspedTime(): void
    {
        Clock::enableMocking();
        $startTime = Clock::microtimeFloat();

        $retryStrategy = RetryStrategyBuilder
            ::create(
                new ConstantDelay(),
                new NoJitter(),
            )
            ->withMaxElapsedTime(Duration::milliSeconds(1010))
            ->build()
        ;

        self::assertTrue($retryStrategy->shouldContinue(0, $startTime));
        self::assertGreaterThan(0, $retryStrategy->getDelay(0)->toMilliSeconds());

        Clock::usleep(20 * 1000);

        self::assertFalse($retryStrategy->shouldContinue(1, $startTime));
    }

    /**
     * @dataProvider maxFailureProvider
     */
    public function testNoRetriesAfterMaxFailures(int $maxFailure): void
    {
        Clock::enableMocking();
        $startTime = Clock::microtimeFloat();

        $retryStrategy = RetryStrategyBuilder
            ::create(
                new ConstantDelay(),
                new NoJitter(),
            )
            ->withMaxFailures($maxFailure)
            ->build()
        ;

        for ($i = 0; $i <= $maxFailure - 1; $i++) {
            self::assertTrue($retryStrategy->shouldContinue($i, $startTime));
            self::assertGreaterThan(0, $retryStrategy->getDelay($i)->toMilliSeconds());

            Clock::sleep(100);
        }

        self::assertFalse($retryStrategy->shouldContinue($maxFailure, $startTime));
    }

    public function testDelayIsClampedToMaxDelay(): void
    {
        Clock::enableMocking();

        $retryStrategy = RetryStrategyBuilder::constant()
            ->withMaxFailures(2)
            ->withMaxDelay(Duration::milliSeconds(100))
            ->build()
        ;

        self::assertSame(100, $retryStrategy->getDelay(1)->toMilliSecondsInt());
    }

    public function testDelayAndJitterStrategiesAreUsed(): void
    {
        Clock::enableMocking();
        $startTime = Clock::microtimeFloat();

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

        self::assertTrue($retryStrategy->shouldContinue(0, $startTime));
        self::assertSame(11, $retryStrategy->getDelay(1)->toMilliSecondsInt());

        self::assertTrue($retryStrategy->shouldContinue(1, $startTime));
        self::assertSame(21, $retryStrategy->getDelay(2)->toMilliSecondsInt());
    }

    public function testToJsonString(): void
    {
        Clock::enableMocking();

        $strategy1 = RetryStrategyBuilder::exponentialInteractive()->build();
        $strategy2 = RetryStrategy::fromJsonString($strategy1->toJsonString());

        self::assertEquals($strategy1, $strategy2);
    }

    /**
     * @return array<array<int>>
     */
    public function maxFailureProvider(): array
    {
        return [
            [0],
            [1],
            [2],
            [10],
        ];
    }
}
