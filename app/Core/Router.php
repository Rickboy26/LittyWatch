<?php
declare(strict_types=1);

namespace LittyWatch\Core;

use Closure;

final class Router
{
    /** @var array<string,array<string,Closure(Request):Response>> */
    private array $routes = [];

    /** @param Closure(Request):Response $handler */
    public function get(string $path, Closure $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    /** @param Closure(Request):Response $handler */
    public function post(string $path, Closure $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$this->normalize($request->path())] ?? null;
        if (!$handler) {
            return Response::html('<h1>404</h1><p>Pagina niet gevonden.</p>', 404);
        }
        return $handler($request);
    }

    private function normalize(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }
}
