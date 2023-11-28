<?php

namespace Mention\Retry\JitterStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Jitter\Jitter;
use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\Internal\MapperHelper;

class JitterJitter implements JitterStrategyInterface
{
    public const JSON_NAME = 'jitter_jitter';

    public function __construct(
        private readonly float $randomizationFactor = 0.5,
    ) {
    }

    public function apply(Duration $delay): Duration
    {
        return Duration::milliSeconds(
            Jitter::random($delay->toMilliSecondsInt(), $this->randomizationFactor),
        );
    }

    public function toJsonString(): string
    {
        return JsonUtils::encode([
            'type' => self::JSON_NAME,
            'randomizationFactor' => $this->randomizationFactor,
        ]);
    }

    public static function fromJsonString(string $str): self
    {
        $params = MapperHelper::mapper()->map(
            'array{randomizationFactor: float}',
            JsonUtils::decodeArray($str),
        );

        return new self($params['randomizationFactor']);
    }
}
