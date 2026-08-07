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
    public function dataQuality(Request $request): Response
    {
        $category=trim($request->string('category','all'));
        $query=trim($request->string('q'));
        $type=trim($request->string('type'));
        if(!in_array($type,['','buy','sell','trade'],true))$type='';
        $limit=(int)$request->string('limit','200');
        $limit=max(25,min(500,$limit));

        return Response::html($this->view->render('admin/data-quality', [
            'title' => 'Data Quality Workbench · LittyWatch',
            'overview' => $this->market->dataQualityOverview(20,20),
            'category' => $category,
            'query' => $query,
            'type' => $type,
            'limit' => $limit,
            'cases' => $this->market->dataQualityCases($category,$query,$type,$limit),
        ]));
    }

}
