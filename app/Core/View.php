<?php
declare(strict_types=1);

namespace LittyWatch\Core;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $basePath) {}

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $content = $this->capture($template, $data);
        return $this->capture($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $data */
    private function capture(string $template, array $data): string
    {
        $file = $this->basePath . '/' . trim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View niet gevonden: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
