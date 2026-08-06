<?php
declare(strict_types=1);

namespace LittyWatch\Core;

final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errorHandler,
    ) {
    }

    public function run(Request $request): never
    {
        try {
            $this->router->dispatch($request)->send();
        } catch (\Throwable $exception) {
            $this->errorHandler->render($exception)->send();
        }
    }
}
