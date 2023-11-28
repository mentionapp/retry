<?php

namespace Mention\Retry;

use Mention\Kebab\Clock\Clock;
use Mention\Kebab\Duration\Duration;
use Mention\Retry\RetryStrategy\RetryStrategy;

class RetrySync
{
    /** @var RetryStrategy */
    private $retryStrategy;

    /** @var int */
    private $failures;

    /**
     * Use Retry::sync().
     */
    public function __construct(RetryStrategy $retryStrategy)
    {
        $this->retryStrategy = $retryStrategy;
        $this->failures = 0;
    }

    /**
     * Calls $function repeatedly until it succeeds or an exit condition occurs.
     *
     * The function is called repeatedly until one of the following conditions
     * is met:
     *
     * - It returned without throwing an exception
     * - It thrown a PermanentError exception, in which case the inner exception
     *   is re-thrown
     * - The RetryStrategy decided that it should not continue
     *
     * Each retry is made after a delay according to the retry strategy.
     *
     * The $notify function is called before the delay. It takes an exception
     * and the interval to the next call as parameters.
     *
     * @template T
     *
     * @param callable(): T                                                   $function
     * @param null|callable(\Exception $exception, Duration $nextDelay): void $notify
     *
     * @phpstan-return T The value returned by $function
     */
    public function retry(callable $function, callable $notify = null)
    {
        $startTime = Clock::microtimeFloat();

        while (true) {
            try {
                return $function();
            } catch (PermanentError $e) {
                throw $e->getPrevious() ?? $e;
            } catch (\Exception $e) {
                $this->failures++;
                if (!$this->retryStrategy->shouldContinue($this->failures, $startTime)) {
                    throw $e;
                }

                $nextDelay = $this->retryStrategy->getDelay($this->failures);

                if (null !== $notify) {
                    $notify($e, $nextDelay);
                }

                $sleepDuration = $nextDelay->toMicroSecondsInt();
                assert($sleepDuration >= 0);

                Clock::usleep($sleepDuration);
            }
        }
    }

    /**
     * @return int Number of failures so far
     */
    public function getFailures(): int
    {
        return $this->failures;
    }
}
