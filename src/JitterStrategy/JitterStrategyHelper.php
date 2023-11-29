<?php

namespace Mention\Retry\JitterStrategy;

use Mention\Kebab\Json\JsonUtils;
use Mention\Retry\Internal\MapperHelper;

final class JitterStrategyHelper
{
    /** @var array<string,string> */
    private static $registry = [
        JitterJitter::JSON_NAME => JitterJitter::class,
        NoJitter::JSON_NAME => NoJitter::class,
    ];

    /**
     * Unserialize a JSON string returned by JitterStrategyInterface::toJsonString().
     */
    public static function fromJsonString(string $str): JitterStrategyInterface
    {
        $params = MapperHelper::mapper()->map(
            'array{type: string}',
            JsonUtils::decodeArray($str),
        );

        $factory = self::$registry[$params['type']] ?? null;

        if (null === $factory) {
            throw new \Exception(sprintf(
                'Unknown jitter type: %s',
                $params['type'],
            ));
        }

        $fromJsonString = [$factory, 'fromJsonString'];

        assert(is_callable($fromJsonString));

        return $fromJsonString($str);
    }
}
