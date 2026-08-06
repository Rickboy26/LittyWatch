<?php
declare(strict_types=1);

namespace LittyWatch\Support;

final class ProjectPaths
{
    public function __construct(private readonly string $root)
    {
    }

    public function root(string $path = ''): string
    {
        return $this->join($this->root, $path);
    }

    public function app(string $path = ''): string
    {
        return $this->root('app' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    public function views(string $path = ''): string
    {
        return $this->app('Views' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    public function pages(string $path = ''): string
    {
        return $this->app('Pages' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    public function config(string $path = ''): string
    {
        return $this->root('config' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    public function logs(string $path = ''): string
    {
        return $this->root('logs' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    private function join(string $base, string $path): string
    {
        if ($path === '') {
            return rtrim($base, '/\\');
        }

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
    }
}
