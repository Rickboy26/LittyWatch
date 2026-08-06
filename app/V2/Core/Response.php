<?php
declare(strict_types=1);

namespace LittyWatch\V2\Core;

final class Response
{
    public static function error(\Throwable $e): never
    {
        http_response_code(500);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>LittyWatch V2 fout</title>';
        echo '<style>body{font-family:system-ui;background:#111827;color:#f9fafb;padding:32px}pre{white-space:pre-wrap;background:#1f2937;padding:16px;border-radius:12px}</style>';
        echo '<h1>V2 kon niet starten</h1><pre>' . $message . '</pre>';
        exit;
    }
}
