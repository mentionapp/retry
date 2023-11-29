<?php

namespace Mention\Retry\JitterStrategy;

use Mention\Kebab\Duration\Duration;

interface JitterStrategyInterface
{
    public function apply(Duration $delay): Duration;

    public function toJsonString(): string;
}
