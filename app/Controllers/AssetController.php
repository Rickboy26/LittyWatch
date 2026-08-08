<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\V2\Assets\AssetCatalogService;
use Throwable;

final class AssetController
{
    public function __construct(
        private readonly AssetCatalogService $assets,
        private readonly View $view,
        private readonly string $root,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    public function update(Request $request): Response
    {
        $message=null;$error=null;
        try {
            $action=$request->string('action','link');
            $assetId=$request->int('asset_id');
            if($assetId<=0) throw new \RuntimeException('Geen geldig icoon gekozen.');
            if($action==='unlink') {
                $this->assets->unlink($assetId);
                $message='Icoonkoppeling verwijderd.';
            } else {
                $item=$request->string('item');
                if($item==='') throw new \RuntimeException('Kies eerst een item.');
                $this->assets->link($assetId,$item);
                $message='Icoon is aan het marktitem gekoppeld.';
            }
        } catch (Throwable $e) { $error=$e->getMessage(); }
        return $this->page($request,$message,$error);
    }

    private function page(Request $request,?string $message=null,?string $error=null): Response
    {
        $directory=$this->root.'/assets/game-items';
        $q=trim($request->string('q'));
        $filter=$request->string('filter','all');
        if(!in_array($filter,['all','linked','unlinked'],true))$filter='all';
        $page=max(1,$request->int('page',1));
        $limit=72;$offset=($page-1)*$limit;
        $summary=$this->assets->summary();
        $rows=$this->assets->assets($q,$filter,$limit,$offset);
        return Response::html($this->view->render('assets/index',[
            'title'=>'Inventory icons · LittyWatch',
            'summary'=>$summary,
            'directory'=>$directory,
            'assets'=>$rows,
            'items'=>$this->assets->marketItems('',1500),
            'q'=>$q,'filter'=>$filter,'page'=>$page,'limit'=>$limit,
            'message'=>$message,'error'=>$error,
        ]));
    }
}
