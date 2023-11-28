<?php

namespace Mention\Retry\Tests\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\DelayStrategy\ConstantDelay;
use Mention\Retry\DelayStrategy\DelayStrategyHelper;
use PHPUnit\Framework\TestCase;

class ConstantDelayTest extends TestCase
{
    public function testConstantDelay(): void
    {
        $delay = new ConstantDelay(Duration::milliSeconds(42));

        self::assertTrue($delay->getDelay(1)->equals(Duration::milliSeconds(42)));
        self::assertTrue($delay->getDelay(2)->equals(Duration::milliSeconds(42)));
        self::assertTrue($delay->getDelay(3)->equals(Duration::milliSeconds(42)));
    }

    public function testToJsonString(): void
    {
        $delay = new ConstantDelay(Duration::milliSeconds(42));
        $delay2 = DelayStrategyHelper::fromJsonString($delay->toJsonString());

        self::assertEquals($delay, $delay2);
    }
}
