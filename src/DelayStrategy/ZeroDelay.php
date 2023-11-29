<?php

namespace Mention\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Json\JsonUtils;

class ZeroDelay implements DelayStrategyInterface
{
    public const JSON_NAME = 'zero';

    public function getDelay(int $iteration): Duration
    {
        return Duration::zero();
    }

    public function toJsonString(): string
    {
        return JsonUtils::encode([
            'type' => self::JSON_NAME,
        ]);
    }

    public static function fromJsonString(string $str): self
    {
        return new self();
    }
}
