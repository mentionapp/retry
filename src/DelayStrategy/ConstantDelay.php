<?php

namespace Mention\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\Internal\MapperHelper;

class ConstantDelay implements DelayStrategyInterface
{
    public const JSON_NAME = 'constant';

    private readonly Duration $delay;

    public function __construct(?Duration $delay = null)
    {
        $this->delay = $delay ?? Duration::second();
    }

    public function withDelay(Duration $delay): self
    {
        return new self($delay);
    }

    public function getDelay(int $iteration): Duration
    {
        return $this->delay;
    }

    public function toJsonString(): string
    {
        return JsonUtils::encode([
            'type' => self::JSON_NAME,
            'delay' => $this->delay->toMilliSecondsInt(),
        ]);
    }

    public static function fromJsonString(string $str): self
    {
        $params = MapperHelper::mapper()->map(
            'array{delay: int}',
            JsonUtils::decodeArray($str),
        );

        return new self(Duration::milliSeconds($params['delay']));
    }
}
