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

    public function autoLink(Request $request): Response
    {
        try {
            $raw=$request->string('payload');
            if($raw==='') throw new \RuntimeException('Geen automatische koppelingen ontvangen.');
            $matches=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($matches)) throw new \RuntimeException('Ongeldige mapper payload.');
            $result=$this->assets->bulkAutoLink($matches);
            return Response::json(['ok'=>true]+$result+['summary'=>$this->assets->summary()]);
        } catch (Throwable $e) {
            return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);
        }
    }

    public function wikiIcon(Request $request): Response
    {
        $file=trim($request->string('file'));
        $file=preg_replace('/^File:/i','',$file) ?? $file;
        if($file===''||strlen($file)>180||!preg_match('/^[^\\\/\x00-\x1F]+\.png$/iu',$file)){
            return Response::json(['ok'=>false,'error'=>'Ongeldige Wiki-iconnaam.'],400);
        }

        $cacheDir=$this->root.'/storage/cache/wiki-inventory-icons';
        if(!is_dir($cacheDir)) @mkdir($cacheDir,0775,true);
        $cacheFile=$cacheDir.'/'.sha1(mb_strtolower($file)).'.png';
        if(is_file($cacheFile)&&filesize($cacheFile)>32){
            $body=(string)file_get_contents($cacheFile);
            if(str_starts_with($body,"\x89PNG\r\n\x1a\n")){
                return new Response($body,200,['Content-Type'=>'image/png','Cache-Control'=>'public, max-age=604800']);
            }
        }

        $url='https://wiki.guildwars.com/wiki/Special:Redirect/file/'.rawurlencode($file);
        $body='';$status=0;$contentType='';
        if(function_exists('curl_init')){
            $ch=curl_init($url);
            if($ch!==false){
                curl_setopt_array($ch,[
                    CURLOPT_RETURNTRANSFER=>true,
                    CURLOPT_FOLLOWLOCATION=>true,
                    CURLOPT_MAXREDIRS=>5,
                    CURLOPT_CONNECTTIMEOUT=>6,
                    CURLOPT_TIMEOUT=>15,
                    CURLOPT_USERAGENT=>'LittyWatch/5.2 inventory-icon mapper (+https://hollandseglory.nl)',
                    CURLOPT_HTTPHEADER=>['Accept: image/png,image/*;q=0.9,*/*;q=0.1'],
                    CURLOPT_SSL_VERIFYPEER=>true,
                ]);
                $raw=curl_exec($ch);
                if(is_string($raw))$body=$raw;
                $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
                $contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
                curl_close($ch);
            }
        }
        if($body===''&&filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
            $ctx=stream_context_create(['http'=>[
                'method'=>'GET','timeout'=>15,'follow_location'=>1,'max_redirects'=>5,
                'header'=>"User-Agent: LittyWatch/5.2 inventory-icon mapper\r\nAccept: image/png,image/*;q=0.9,*/*;q=0.1\r\n",
            ]]);
            $raw=@file_get_contents($url,false,$ctx);
            if(is_string($raw)){$body=$raw;$status=200;}
        }

        if($status>=400||$body===''||!str_starts_with($body,"\x89PNG\r\n\x1a\n")){
            return Response::json(['ok'=>false,'error'=>'Wiki inventory icon kon niet via de server worden gelezen.','file'=>$file,'status'=>$status,'content_type'=>$contentType],502);
        }
        if(is_dir($cacheDir)&&is_writable($cacheDir)) @file_put_contents($cacheFile,$body,LOCK_EX);
        return new Response($body,200,['Content-Type'=>'image/png','Cache-Control'=>'public, max-age=604800']);
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
            'items'=>$this->assets->marketItems('',3000),
            'autoItems'=>$this->assets->unlinkedMarketItems(3000),
            'q'=>$q,'filter'=>$filter,'page'=>$page,'limit'=>$limit,
            'message'=>$message,'error'=>$error,
        ]));
    }
}
