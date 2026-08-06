<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;

final class PageController
{
    public function __construct(private readonly string $root) {}

    public function show(Request $request, string $page): Response
    {
        $allowed = ['live','traders','trader','trends','intelligence','watchlist','alerts','assets','system'];
        if (!in_array($page, $allowed, true)) {
            return Response::html('<h1>404</h1><p>Pagina niet gevonden.</p>', 404);
        }
        $file = $this->root . '/app/Pages/' . $page . '.php';
        ob_start();
        require $file;
        return Response::html((string)ob_get_clean());
    }
}
