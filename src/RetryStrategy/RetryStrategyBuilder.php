<?php

namespace Mention\Retry\RetryStrategy;

use Mention\Kebab\Duration\Duration;
use Mention\Retry\DelayStrategy\ConstantDelay;
use Mention\Retry\DelayStrategy\DelayStrategyInterface;
use Mention\Retry\DelayStrategy\ExponentialDelay;
use Mention\Retry\DelayStrategy\ZeroDelay;
use Mention\Retry\JitterStrategy\JitterJitter;
use Mention\Retry\JitterStrategy\JitterStrategyInterface;
use Mention\Retry\JitterStrategy\NoJitter;

final class RetryStrategyBuilder
{
    private ?int $maxFailures = null;

    private Duration $maxDelay;

    private ?Duration $maxElapsedTime = null;

    private function __construct(
        private DelayStrategyInterface $delayStrategy,
        private JitterStrategyInterface $jitterStrategy,
    ) {
        $this->maxDelay = Duration::minute();
    }

    public static function create(
        DelayStrategyInterface $delayStrategy,
        JitterStrategyInterface $jitterStrategy,
    ): self {
        return new self($delayStrategy, $jitterStrategy);
    }

    /**
     * Creates a builder with an exponential delay and settings suitable for use in web context.
     */
    public static function exponentialInteractive(): self
    {
        $builder = new self(
            new ExponentialDelay(),
            new JitterJitter(),
        );

        return $builder->withMaxElapsedTime(Duration::minute());
    }

    /**
     * Creates a builder with an exponential delay and settings suitable for use in command line / batch context.
     */
    public static function exponentialBatch(): self
    {
        $builder = new self(
            new ExponentialDelay(),
            new JitterJitter(),
        );

        return $builder->withMaxElapsedTime(Duration::minutes(15));
    }

    /**
     * Creates a builder with constant 1 second delay, and maximum 10 failures.
     */
    public static function constant(): self
    {
        $builder = new self(
            new ConstantDelay(),
            new NoJitter(),
        );

        return $builder->withMaxFailures(10);
    }

    /**
     * Creates a builder with no delay, and maximum 10 failures.
     */
    public static function immediate(): self
    {
        $builder = new self(
            new ZeroDelay(),
            new NoJitter(),
        );

        return $builder->withMaxFailures(10);
    }

    /**
     * Creates a builder that disables retries (maximum 1 failure).
     */
    public static function noRetry(): self
    {
        $builder = new self(
            new ZeroDelay(),
            new NoJitter(),
        );

        return $builder->withMaxFailures(1);
    }

    public function withDelayStrategy(DelayStrategyInterface $delayStrategy): self
    {
        $builder = clone $this;
        $builder->delayStrategy = $delayStrategy;

        return $builder;
    }

    public function withJitterStrategy(JitterStrategyInterface $jitterStrategy): self
    {
        $builder = clone $this;
        $builder->jitterStrategy = $jitterStrategy;

        return $builder;
    }

    /**
     * Set the maximum number of failures.
     *
     * Prefer withMaxElapsedTime().
     */
    public function withMaxFailures(?int $maxFailures): self
    {
        $builder = clone $this;
        $builder->maxFailures = $maxFailures;

        return $builder;
    }

    /**
     * Set the max delay.
     */
    public function withMaxDelay(Duration $maxDelay): self
    {
        $builder = clone $this;
        $builder->maxDelay = $maxDelay;

        return $builder;
    }

    /**
     * Set the maximum time during which retries are attempted.
     *
     * @param ?Duration $maxElapsedTime Max elapsed time
     */
    public function withMaxElapsedTime(?Duration $maxElapsedTime): self
    {
        $builder = clone $this;
        $builder->maxElapsedTime = $maxElapsedTime;

        return $builder;
    }

    public function build(): RetryStrategy
    {
        return new RetryStrategy(
            $this->delayStrategy,
            $this->jitterStrategy,
            $this->maxElapsedTime,
            $this->maxFailures,
            $this->maxDelay,
        );
    }
}
