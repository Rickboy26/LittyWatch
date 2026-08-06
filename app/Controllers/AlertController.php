<?php
declare(strict_types=1);
namespace LittyWatch\Controllers;
use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Services\AlertService;use LittyWatch\Services\CurrencyDisplayService;use LittyWatch\Repositories\WatchlistRepository;
final class AlertController{
 public function __construct(private readonly AlertService $service,private readonly WatchlistRepository $markets,private readonly CurrencyDisplayService $money,private readonly View $view){}
 public function index(Request $r):Response{return $this->page();}
 public function update(Request $r):Response{$result=null;$error=null;try{switch($r->string('action','create')){case'evaluate':$result=$this->service->evaluate();break;case'toggle':$this->service->toggle($r->int('id'));break;case'delete':$this->service->delete($r->int('id'));break;case'read':$this->service->markRead($r->int('event_id'));break;case'read_all':$this->service->markAllRead();break;default:$type=$r->string('condition_type');$threshold=$this->price($r->post['threshold_ecto']??null);$this->service->create($r->string('market_key'),$r->string('label'),$type,$threshold);break;}}catch(\Throwable $e){$error=$e->getMessage();}return $this->page($result,$error);}
 private function page(?array $result=null,?string $error=null):Response{return Response::html($this->view->render('alerts/index',['title'=>'Alerts · LittyWatch','alerts'=>$this->service->all(),'events'=>$this->service->events(100),'unread'=>$this->service->unreadCount(),'markets'=>$this->markets->marketOptions('',500),'money'=>$this->money,'result'=>$result,'error'=>$error]));}
 private function price(mixed $v):?float{$v=trim((string)$v);return $v===''?null:(float)str_replace(',','.',$v);}
}
