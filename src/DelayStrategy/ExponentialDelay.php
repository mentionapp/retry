<?php

namespace Mention\Retry\DelayStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\Internal\MapperHelper;

class ExponentialDelay implements DelayStrategyInterface
{
    public const JSON_NAME = 'exponential';

    private readonly Duration $initialDelay;

    private readonly float $multiplier;

    public function __construct(
        ?Duration $initialDelay = null,
        float $multiplier = 1.5,
    ) {
        $this->initialDelay = $initialDelay ?? Duration::milliSeconds(10);
        $this->multiplier = $multiplier;
    }

    public function withInitialDelay(Duration $delay): self
    {
        return new self($delay, $this->multiplier);
    }

    public function withMultiplier(float $mul): self
    {
        return new self($this->initialDelay, $mul);
    }

    public function getDelay(int $iteration): Duration
    {
        $exp = $iteration - 1;
        $base = $this->multiplier;
        $multiplier = 0;

        if ($exp * log($base) < PHP_INT_MAX) {
            $multiplier = pow($base, $exp);
        } else {
            $multiplier = PHP_INT_MAX;
        }

        try {
            return $this->initialDelay->mul($multiplier);
        } catch (\OverflowException $e) {
            return Duration::maxDuration();
        }
    }

    public function toJsonString(): string
    {
        return JsonUtils::encode([
            'type' => self::JSON_NAME,
            'initialDelay' => $this->initialDelay->toMilliSecondsInt(),
            'multiplier' => $this->multiplier,
        ]);
    }

    public static function fromJsonString(string $str): self
    {
        $params = MapperHelper::mapper()->map(
            'array{initialDelay: int, multiplier: float}',
            JsonUtils::decodeArray($str),
        );

        return new self(
            Duration::milliSeconds($params['initialDelay']),
            $params['multiplier'],
        );
    }
}
