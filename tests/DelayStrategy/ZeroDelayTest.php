<?php

namespace Mention\Retry\Tests\Retry\DelayStrategy;

use Mention\Retry\DelayStrategy\DelayStrategyHelper;
use Mention\Retry\DelayStrategy\ZeroDelay;
use PHPUnit\Framework\TestCase;

class ZeroDelayTest extends TestCase
{
    public function testZeroDelay(): void
    {
        $delay = new ZeroDelay();
        self::assertSame(0, $delay->getDelay(1)->toMilliSecondsInt());
    }

    public function testToJsonString(): void
    {
        $delay = new ZeroDelay();

        $delay2 = DelayStrategyHelper::fromJsonString($delay->toJsonString());

        self::assertEquals($delay, $delay2);
    }
}
