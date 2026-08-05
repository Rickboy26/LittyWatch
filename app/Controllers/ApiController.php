<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Services\DashboardService;

final class ApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function dashboard(Request $request): Response
    {
        $limit = max(10, min(300, $request->int('limit', 100)));
        $data = $this->dashboard->build(
            $request->string('q'),
            $request->string('type'),
            $request->string('status'),
            $limit,
        );

        return Response::json([
            'ok' => true,
            'generated_at' => gmdate('c'),
            'data' => $data,
        ]);
    }
}
