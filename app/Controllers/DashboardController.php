<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Services\DashboardService;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly View $view,
    ) {}

    public function index(Request $request): Response
    {
        $data = $this->dashboard->build(
            $request->string('q'),
            $request->string('type'),
            $request->string('status'),
            max(10, min(300, $request->int('limit', 100))),
        );
        return Response::html($this->view->render('dashboard/index', $data));
    }
}
