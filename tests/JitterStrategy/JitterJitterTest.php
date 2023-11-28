<?php

namespace Mention\Retry\Tests\Retry\JitterStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\JitterStrategy\JitterJitter;
use Mention\Retry\JitterStrategy\JitterStrategyHelper;
use PHPUnit\Framework\TestCase;

class JitterJitterTest extends TestCase
{
    public function testJitter(): void
    {
        $jitter = new JitterJitter();
        $deviated = 0;

        for ($i = 0; $i < 10; $i++) {
            if (!$jitter->apply(Duration::milliSeconds(10))->equals(Duration::milliSeconds(10))) {
                $deviated++;
            }
        }

        self::assertGreaterThan(1, $deviated);
    }

    public function testToJsonString(): void
    {
        $jitter = new JitterJitter(123);
        $jitter2 = JitterStrategyHelper::fromJsonString($jitter->toJsonString());

        self::assertEquals($jitter, $jitter2);
    }
}
