<?php

namespace Mention\Retry;

class PermanentError extends \Exception
{
    public static function withException(\Exception $exception): self
    {
        return new self('Permanent Error', 0, $exception);
    }
}
