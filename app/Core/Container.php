<?php
declare(strict_types=1);

namespace LittyWatch\Core;

use Closure;
use RuntimeException;

final class Container
{
    /** @var array<string,Closure(self):mixed> */
    private array $factories = [];
    /** @var array<string,mixed> */
    private array $instances = [];

    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->set($id, function (self $container) use ($id, $factory): mixed {
            return $this->instances[$id] ??= $factory($container);
        });
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Service niet geregistreerd: {$id}");
        }
        return ($this->factories[$id])($this);
    }
}
