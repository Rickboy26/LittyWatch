<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Knowledge\KnowledgeControllerData;

final class KnowledgeController
{
    public function __construct(private readonly KnowledgeControllerData $data, private readonly View $view) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('knowledge/index', $this->data->get() + ['title' => 'Knowledge Base']));
    }
}
