<?php
declare(strict_types=1);

namespace LittyWatch\Core;

use Throwable;

final class ErrorHandler
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function render(Throwable $exception): Response
    {
        $errorId = 'LW-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->writeLog($errorId, $exception);

        $debug = (bool)($this->config['debug'] ?? false);
        $message = $debug
            ? nl2br(htmlspecialchars((string)$exception, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            : 'Deze pagina kon niet worden geladen. Foutnummer: ' . $errorId;

        return Response::html(
            '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fout · LittyWatch</title><style>body{background:#080b10;color:#eef2f8;font:16px/1.55 system-ui;max-width:760px;margin:80px auto;padding:20px}a{color:#d9b870}.box{background:#121824;border:1px solid #293548;border-radius:16px;padding:24px}</style></head><body><main class="box"><h1>Er ging iets mis</h1><p>' . $message . '</p><p><a href="/">Terug naar LittyWatch</a></p></main></body></html>',
            500,
        );
    }

    public function renderJson(Throwable $exception): Response
    {
        $errorId = 'LW-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->writeLog($errorId, $exception);

        return Response::json([
            'ok' => false,
            'error' => (bool)($this->config['debug'] ?? false)
                ? $exception->getMessage()
                : 'De batchactie kon niet worden uitgevoerd.',
            'error_id' => $errorId,
            'error_type' => $exception::class,
        ], 500);
    }

    private function writeLog(string $errorId, Throwable $exception): void
    {
        $logPath = (string)($this->config['log_path'] ?? '');
        if ($logPath === '') {
            return;
        }

        $directory = dirname($logPath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents(
            $logPath,
            '[' . date(DATE_ATOM) . '] ' . $errorId . ' ' . (string)$exception . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
