<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Services\ItemImageService;
use LittyWatch\Repositories\MarketRepository;
use LittyWatch\Repositories\DatasetRepository;

final class AdminController
{
    public function __construct(private readonly View $view, private readonly ItemImageService $images, private readonly MarketRepository $market, private readonly DatasetRepository $dataset) {}

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/index', [
            'title' => 'Beheer · LittyWatch',
            'imageItems' => $this->images->all(),
            'dataQuality' => $this->market->dataQualityOverview(),
        ]));
    }
    public function dataset(Request $request): Response
    {
        return Response::html($this->view->render('admin/dataset', [
            'title'=>'Kamadan Dataset · LittyWatch',
            'summary'=>$this->dataset->summary(),
            'patterns'=>$this->dataset->patterns(),
            'reviewReasons'=>$this->dataset->reviewReasons(),
            'collectorStatus'=>$this->collectorStatus(),
        ]));
    }

    private function collectorStatus(): array
    {
        $path=dirname(__DIR__,2).'/storage/kamadan-collector-status.json';
        if(!is_file($path)) return [];
        $decoded=json_decode((string)file_get_contents($path),true);
        return is_array($decoded)?$decoded:[];
    }

    public function datasetExport(Request $request): Response
    {
        $lines=[]; foreach($this->dataset->exportRows() as $row)$lines[]=json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return new Response(implode("\n",$lines)."\n",200,['Content-Type'=>'application/x-ndjson; charset=utf-8','Content-Disposition'=>'attachment; filename=littywatch-training-dataset.ndjson']);
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
