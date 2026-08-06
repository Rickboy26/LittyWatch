<?php
declare(strict_types=1);
namespace LittyWatch\Controllers;
use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Repositories\StructuredMarketRepository;
final class StructuredMarketController{
 public function __construct(private readonly StructuredMarketRepository $repo,private readonly View $view){}
 public function index(Request$r):Response{$q=$r->string('q');$status=$r->string('status','active');if(!in_array($status,['active','all'],true))$status='active';return Response::html($this->view->render('markets/index',['title'=>'Markten · LittyWatch','query'=>$q,'status'=>$status,'markets'=>$this->repo->directory($q,300,$status),'lifecycle'=>$this->repo->lifecycleStats(),'spreads'=>$this->repo->biggestSpreads()]));}
 public function show(Request$r):Response{$key=$r->string('key');$m=$this->repo->detail($key);if(!$m)return Response::html('<h1>Markt niet gevonden</h1>',404);return Response::html($this->view->render('markets/show',['title'=>$m['item'].' · LittyWatch','market'=>$m,'offers'=>$this->repo->offers($key),'analytics'=>$this->repo->analytics($key)]));}
}
