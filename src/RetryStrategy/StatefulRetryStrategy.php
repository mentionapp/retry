<?php

namespace Mention\Retry\RetryStrategy;

use Mention\Kebab\Clock\Clock;
use Mention\Kebab\Duration\Duration;

/**
 * A conveniency stateful variant of RetryStrategy.
 *
 * This conveniency variant of RetryStrategy keeps count of the start time and
 * number of failures.
 */
class StatefulRetryStrategy
{
    private int $failures;

    /** @var float Start time in seconds */
    private readonly float $startTime;

    public function __construct(
        private readonly RetryStrategy $retryStrategy,
    ) {
        $this->failures = 0;
        $this->startTime = Clock::microtimeFloat();
    }

    /**
     * @return bool Whether the client should continue
     */
    public function shouldContinue(): bool
    {
        return $this->retryStrategy->shouldContinue(
            $this->failures,
            $this->startTime,
        );
    }

    /**
     * Takes one failure into account.
     */
    public function addFailure(): self
    {
        $this->failures++;

        return $this;
    }

    /**
     * @return int Number of failures so far
     */
    public function getFailures(): int
    {
        return $this->failures;
    }

    /**
     * @return Duration The duration to wait before retrying
     */
    public function getDelay(): Duration
    {
        return $this->retryStrategy->getDelay($this->failures);
    }
}
