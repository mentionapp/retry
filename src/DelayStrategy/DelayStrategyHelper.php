<?php

namespace Mention\Retry\DelayStrategy;

use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\Internal\MapperHelper;

final class DelayStrategyHelper
{
    /** @var array<string,class-string<DelayStrategyInterface>> */
    private static $registry = [
        ConstantDelay::JSON_NAME => ConstantDelay::class,
        ExponentialDelay::JSON_NAME => ExponentialDelay::class,
        ZeroDelay::JSON_NAME => ZeroDelay::class,
    ];

    /**
     * Unserialize a JSON string returned by DelayStrategyInterface::toJsonString().
     */
    public static function fromJsonString(string $str): DelayStrategyInterface
    {
        $params = MapperHelper::mapper()->map(
            'array{type: string}',
            JsonUtils::decodeArray($str),
        );

        $factory = self::$registry[$params['type']] ?? null;

        if (null === $factory) {
            throw new \Exception(sprintf(
                'Unknown delay type: %s',
                $params['type'],
            ));
        }

        $fromJsonString = [$factory, 'fromJsonString'];

        assert(is_callable($fromJsonString));

        return $fromJsonString($str);
    }
}
