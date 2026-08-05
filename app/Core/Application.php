<?php
declare(strict_types=1);

namespace LittyWatch\Core;

final class Application
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
    ) {}

    public function run(Request $request): never
    {
        try {
            $this->router->dispatch($request)->send();
        } catch (\Throwable $e) {
            $debug = (bool)($this->container->get('config')['debug'] ?? false);
            $message = $debug ? nl2br(htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8')) : 'Er ging iets mis.';
            Response::html('<h1>500</h1><p>'.$message.'</p>', 500)->send();
        }
    }
}
