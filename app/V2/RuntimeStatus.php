<?php

declare(strict_types=1);

namespace LittyWatch\V2;

final class RuntimeStatus
{
    public static function write(string $root, string $name, array $payload): void
    {
        $dir = rtrim($root, DIRECTORY_SEPARATOR) . '/data/runtime';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $payload['name'] = $name;
        $payload['updated_at'] = date(DATE_ATOM);
        @file_put_contents($dir . '/' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '.json', json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function read(string $root, string $name): ?array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . '/data/runtime/' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '.json';
        if (!is_file($path)) return null;
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public static function ageSeconds(?array $status): ?int
    {
        if (!$status || empty($status['updated_at'])) return null;
        $time = strtotime((string)$status['updated_at']);
        return $time === false ? null : max(0, time() - $time);
    }
}
