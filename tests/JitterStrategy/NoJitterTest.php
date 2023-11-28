<?php

namespace Mention\Retry\Tests\Retry\JitterStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\JitterStrategy\JitterStrategyHelper;
use Mention\Retry\JitterStrategy\NoJitter;
use PHPUnit\Framework\TestCase;

class NoJitterTest extends TestCase
{
    public function testNoJitter(): void
    {
        $jitter = new NoJitter();
        self::assertSame(10, $jitter->apply(Duration::milliSeconds(10))->toMilliSecondsInt());
    }

    public function testToJsonString(): void
    {
        $jitter = new NoJitter();
        $jitter2 = JitterStrategyHelper::fromJsonString($jitter->toJsonString());

        self::assertEquals($jitter, $jitter2);
    }
}
