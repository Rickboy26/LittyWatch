<?php
declare(strict_types=1);
namespace LittyWatch\Controllers;
use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Repositories\ParserReviewRepository;
final class ParserReviewController{
 public function __construct(private readonly ParserReviewRepository$repo,private readonly View$view){}
 public function index(Request$r):Response{$this->repo->seedPending();$status=$r->string('status','pending');$quality=$r->string('quality');$q=$r->string('q');return Response::html($this->view->render('reviews/index',['title'=>'Parser Review · LittyWatch','summary'=>$this->repo->summary(),'qualityStats'=>$this->repo->qualityStats(),'rows'=>$this->repo->queue($status,$quality,$q,200),'terms'=>$this->repo->commonTerms(),'knowledge'=>$this->repo->knowledge(),'status'=>$status,'quality'=>$quality,'query'=>$q,'message'=>$r->string('message')]));}
 public function update(Request$r):Response{$action=$r->string('action','review');if($action==='review'){$this->repo->save($r->int('id'),$r->string('review_status'),$r->string('expected_item'),$r->string('expected_requirement')!==''?$r->int('expected_requirement'):null,$r->string('expected_attribute'),$r->string('expected_market_key'),$r->string('notes'),$r->string('alias'));}else{$this->repo->knowledgeAction($action,$_POST);}return new Response('',302,['Location'=>'/parser-review?status='.$r->string('return_status','pending').'&message='.rawurlencode('Opgeslagen')]);}
 public function export(Request$r):Response{return Response::json(['version'=>'v4.3','exported_at'=>date(DATE_ATOM),'cases'=>$this->repo->export()]);}
}
