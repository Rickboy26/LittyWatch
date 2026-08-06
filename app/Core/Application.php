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
            $errorId = 'LW-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $config = $this->container->get('config');
            $logPath = (string)($config['log_path'] ?? '');
            if ($logPath !== '') { @file_put_contents($logPath, '[' . date(DATE_ATOM) . '] ' . $errorId . ' ' . (string)$e . PHP_EOL, FILE_APPEND|LOCK_EX); }
            $message = $debug ? nl2br(htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8')) : 'Deze pagina kon niet worden geladen. Foutnummer: ' . $errorId;
            Response::html('<!doctype html><meta charset="utf-8"><title>Fout · LittyWatch</title><style>body{background:#080b10;color:#eef2f8;font:16px system-ui;max-width:760px;margin:80px auto;padding:20px}a{color:#d9b870}</style><h1>Er ging iets mis</h1><p>'.$message.'</p><p><a href="/">Terug naar LittyWatch</a></p>', 500)->send();
        }
    }
}
