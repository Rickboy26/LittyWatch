<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Support\ProjectPaths;
use RuntimeException;

final class PageController
{
    /** @var list<string> */
    private const ALLOWED_PAGES = [
        'live',
        'traders',
        'trader',
        'trends',
        'intelligence',
        'assets',
        'system',
    ];

    public function __construct(private readonly ProjectPaths $paths)
    {
    }

    public function show(Request $request, string $page): Response
    {
        if (!in_array($page, self::ALLOWED_PAGES, true)) {
            return Response::html('<h1>404</h1><p>Pagina niet gevonden.</p>', 404);
        }

        $file = $this->paths->pages($page . '.php');
        if (!is_file($file)) {
            throw new RuntimeException('Featurepagina ontbreekt: ' . $page);
        }

        // Tijdelijke compatibiliteitslaag voor de nog niet gemigreerde featurepagina's.
        // In V4 fase 2 worden deze pagina's opgesplitst in controllers, services en views.
        $root = $this->paths->root();
        ob_start();
        require $file;

        return Response::html((string)ob_get_clean());
    }
}
