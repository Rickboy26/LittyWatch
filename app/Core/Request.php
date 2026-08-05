<?php
declare(strict_types=1);

namespace LittyWatch\Core;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $post @param array<string,mixed> $server */
    public function __construct(
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
    ) {}

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? rtrim($path, '/') ?: '/' : '/';
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $this->post[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }
}
