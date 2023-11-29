<?php

namespace Mention\Retry\Tests\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\DelayStrategy\DelayStrategyHelper;
use Mention\Retry\DelayStrategy\ExponentialDelay;
use PHPUnit\Framework\TestCase;

class ExponentialDelayTest extends TestCase
{
    public function testExponentialDelay(): void
    {
        $delay = (new ExponentialDelay())
            ->withInitialDelay(Duration::milliSeconds(10))
            ->withMultiplier(1.5)
        ;

        self::assertEquals(10, $delay->getDelay(1)->toMilliSecondsInt());
        self::assertEquals(intval(10 * 1.5), $delay->getDelay(2)->toMilliSecondsInt());
        self::assertEquals(intval(10 * 1.5 * 1.5), $delay->getDelay(3)->toMilliSecondsInt());
    }

    public function testExponentialDelayDoesNotOverflow(): void
    {
        $delay = (new ExponentialDelay())
            ->withInitialDelay(Duration::milliSeconds(10))
            ->withMultiplier(1.5)
        ;

        self::assertEquals(Duration::maxDuration()->toMilliSecondsInt(), $delay->getDelay(1000)->toMilliSecondsInt());
    }

    public function testToJsonString(): void
    {
        $delay = (new ExponentialDelay())
            ->withInitialDelay(Duration::milliSeconds(123))
            ->withMultiplier(42)
        ;

        $delay2 = DelayStrategyHelper::fromJsonString($delay->toJsonString());

        self::assertEquals($delay, $delay2);
    }
}
