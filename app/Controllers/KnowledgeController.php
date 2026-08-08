<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Knowledge\KnowledgeControllerData;
use Throwable;

final class KnowledgeController
{
    public function __construct(private readonly KnowledgeControllerData $data, private readonly View $view) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('knowledge/index', $this->data->get() + ['title' => 'Knowledge Base']));
    }
    public function importGwMarket(Request $request): Response
    {
        try {
            $category=trim($request->string('category'));
            $json=$request->string('json');
            if($json==='') throw new \RuntimeException('Geen catalogusdata ontvangen.');
            if(strlen($json)>8_000_000) throw new \RuntimeException('Catalogusbestand is te groot.');
            $result=$this->data->importGwMarket($category,$json);
            return Response::json(['ok'=>true]+$result);
        } catch (Throwable $e) {
            return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);
        }
    }

}
