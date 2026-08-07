<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Services\KnowledgePackService;
use Throwable;

final class KnowledgePackController
{
    public function __construct(
        private readonly KnowledgePackService $service,
        private readonly View $view,
    ) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('knowledge-pack/index', [
            'title'=>'GW Knowledge Pack · LittyWatch',
            ...$this->service->dashboard(),
            'message'=>$request->string('message'),
            'error'=>$request->string('error'),
        ]));
    }

    public function stage(Request $request): Response
    {
        try {
            $payload = json_decode($request->string('payload'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) throw new \RuntimeException('Ongeldige Wiki-batch.');

            return Response::json([
                'ok'=>true,
                ...$this->service->stage(
                    $request->string('profile'),
                    $request->string('kind'),
                    $payload
                ),
            ]);
        } catch (Throwable $exception) {
            return Response::json([
                'ok'=>false,
                'error'=>$exception->getMessage(),
            ], 500);
        }
    }

    public function compile(Request $request): Response
    {
        try {
            $result = $this->service->compile();
            return Response::json(['ok'=>true,...$result]);
        } catch (Throwable $exception) {
            return Response::json(['ok'=>false,'error'=>$exception->getMessage()],500);
        }
    }

    public function clear(Request $request): Response
    {
        $this->service->clearStage();
        return Response::json(['ok'=>true]);
    }
}
