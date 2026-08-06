<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Services\ItemImageService;

final class AdminController
{
    public function __construct(private readonly View $view, private readonly ItemImageService $images) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/index', [
            'title' => 'Beheer · LittyWatch',
            'imageItems' => $this->images->all(),
        ]));
    }
}
