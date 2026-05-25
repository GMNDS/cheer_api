<?php

namespace Cheer\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if ($file === null || $file === '') {
            return $default;
        }

        $config = self::load($file);

        foreach ($segments as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }

            $config = $config[$segment];
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private static function load(string $file): array
    {
        if (!array_key_exists($file, self::$items)) {
            $path = dirname(__DIR__, 2) . "/config/{$file}.php";

            self::$items[$file] = is_file($path) ? require $path : [];
        }

        return self::$items[$file];
    }
}
