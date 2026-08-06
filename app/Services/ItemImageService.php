<?php
declare(strict_types=1);

namespace LittyWatch\Services;

final class ItemImageService
{
    /** @var array<string,string> */
    private array $titles;

    public function __construct(private readonly string $root)
    {
        $config = require $root . '/config/item-images.php';
        $this->titles = is_array($config) ? $config : [];
    }

    public function url(string $item, int $size = 64): string
    {
        $size = max(32, min(256, $size));
        return '/item-image.php?item=' . rawurlencode($item) . '&size=' . $size;
    }

    public function hasKnownSource(string $item): bool
    {
        return isset($this->titles[$item]);
    }

    public function wikiTitle(string $item): ?string
    {
        return $this->titles[$item] ?? null;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        return $this->titles;
    }
}
