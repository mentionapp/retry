<?php

namespace Mention\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;

interface DelayStrategyInterface
{
    public function getDelay(int $iteration): Duration;

    public function toJsonString(): string;
}
