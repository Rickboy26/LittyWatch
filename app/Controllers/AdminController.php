<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Services\ItemImageService;
use LittyWatch\Repositories\MarketRepository;

final class AdminController
{
    public function __construct(private readonly View $view, private readonly ItemImageService $images, private readonly MarketRepository $market) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/index', [
            'title' => 'Beheer · LittyWatch',
            'imageItems' => $this->images->all(),
            'dataQuality' => $this->market->dataQualityOverview(),
        ]));
    }
}
