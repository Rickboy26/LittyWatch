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
            if (
                $request->path() === '/parser-review/re-evaluate'
                || str_contains(
                    strtolower((string)($request->server['HTTP_ACCEPT'] ?? '')),
                    'application/json'
                )
            ) {
                $this->errorHandler->renderJson($exception)->send();
            }

            $this->errorHandler->render($exception)->send();
        }
    }
}
