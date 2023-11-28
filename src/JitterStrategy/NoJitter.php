<?php

namespace Mention\Retry\JitterStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Json\JsonUtils;

class NoJitter implements JitterStrategyInterface
{
    public const JSON_NAME = 'no_jitter';

    public function apply(Duration $delay): Duration
    {
        return $delay;
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
