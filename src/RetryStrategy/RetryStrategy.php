<?php

namespace Mention\Retry\RetryStrategy;

use Mention\Kebab\Clock\Clock;
use Mention\Kebab\Duration\Duration;
use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\DelayStrategy\DelayStrategyHelper;
use Mention\Retry\DelayStrategy\DelayStrategyInterface;
use Mention\Retry\Internal\MapperHelper;
use Mention\Retry\JitterStrategy\JitterStrategyHelper;
use Mention\Retry\JitterStrategy\JitterStrategyInterface;

class RetryStrategy
{
    /**
     * @internal Use RetryStrategyBuilder
     */
    public function __construct(
        private readonly DelayStrategyInterface $delayStrategy,
        private readonly JitterStrategyInterface $jitterStrategy,
        private readonly ?Duration $maxElapsedTime,
        private readonly ?int $maxFailures,
        private readonly Duration $maxDelay,
    ) {
        if (null === $this->maxFailures && null === $this->maxElapsedTime) {
            throw new \InvalidArgumentException('RetryStrategy must have a max elapsed time or max failures');
        }
    }

    /**
     * @param int   $failures  The number of failures so far
     * @param float $startTime Start time of the first try, in seconds
     *
     * @return bool Whether the client should continue
     */
    public function shouldContinue(int $failures, float $startTime): bool
    {
        $maxFailures = $this->maxFailures;
        if (null !== $maxFailures) {
            return $failures < $maxFailures;
        }

        if (null !== $this->maxElapsedTime) {
            $elapsed = Duration
                ::milliSeconds(intval((Clock::microtimeFloat() - $startTime) * 1000))
                ->add($this->getDelay($failures))
            ;

            return $elapsed->lessThan($this->maxElapsedTime);
        }

        return true;
    }

    /**
     * @return Duration The duration to wait before retrying
     */
    public function getDelay(int $failures): Duration
    {
        $delay = $this->delayStrategy->getDelay($failures);
        $delay = $delay->min($this->maxDelay);
        $delay = $this->jitterStrategy->apply($delay);
        $delay = $delay->max(Duration::milliSecond());

        return $delay;
    }

    /**
     * Serializes this RetryStrategy instance to a JSON string.
     */
    public function toJsonString(): string
    {
        return JsonUtils::encode([
            'delay' => $this->delayStrategy->toJsonString(),
            'jitter' => $this->jitterStrategy->toJsonString(),
            'maxFailures' => $this->maxFailures,
            'maxDelay' => $this->maxDelay->toMilliSecondsInt(),
            'maxElapsedTime' => $this->maxElapsedTime?->toMilliSecondsInt(),
        ]);
    }

    /**
     * Unserializes a JSON string produced by toJsonString().
     */
    public static function fromJsonString(string $str): self
    {
        $params = MapperHelper::mapper()->map(
            'array{delay: string, jitter: string, maxFailures: ?int, maxDelay: int, maxElapsedTime: ?int}',
            JsonUtils::decodeArray($str),
        );

        return RetryStrategyBuilder
            ::create(
                DelayStrategyHelper::fromJsonString($params['delay']),
                JitterStrategyHelper::fromJsonString($params['jitter']),
            )
            ->withMaxFailures($params['maxFailures'])
            ->withMaxDelay(Duration::milliSeconds($params['maxDelay']))
            ->withMaxElapsedTime($params['maxElapsedTime'] !== null ? Duration::milliSeconds($params['maxElapsedTime']) : null)
            ->build()
        ;
    }
}
