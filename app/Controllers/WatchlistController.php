<?php
declare(strict_types=1);
namespace LittyWatch\Controllers;
use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Services\WatchlistService;
final class WatchlistController{
 public function __construct(private readonly WatchlistService $service,private readonly View $view){}
 public function index(Request $r):Response{return $this->page();}
 public function update(Request $r):Response{$message=null;$error=null;try{$action=$r->string('action','save');if($action==='remove'){$message=$this->service->remove($r->int('id'))?'Item van de watchlist verwijderd.':'Item niet gevonden.';}else{$this->service->save($r->string('market_key'),$r->string('label'),$this->price($r->post['target_buy_ecto']??null),$this->price($r->post['target_sell_ecto']??null));$message='Watchlist en koersdoelen bijgewerkt.';}}catch(\Throwable $e){$error=$e->getMessage();}return $this->page($message,$error);}
 private function page(?string $message=null,?string $error=null):Response{return Response::html($this->view->render('watchlist/index',['title'=>'Watchlist · LittyWatch','rows'=>$this->service->all(),'options'=>$this->service->marketOptions('',500),'message'=>$message,'error'=>$error]));}
 private function price(mixed $v):?float{$v=trim((string)$v);return $v===''?null:(float)str_replace(',','.',$v);}
}
