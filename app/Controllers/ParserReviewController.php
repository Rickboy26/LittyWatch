<?php
declare(strict_types=1);
namespace LittyWatch\Controllers;
use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Repositories\ParserReviewRepository;
final class ParserReviewController{
 public function __construct(private readonly ParserReviewRepository $repo,private readonly View $view){}
 public function index(Request$r):Response{$this->repo->seedPending();$status=$r->string('status','pending');$quality=$r->string('quality');$q=$r->string('q');return Response::html($this->view->render('reviews/index',['title'=>'Parser Review · LittyWatch','summary'=>$this->repo->summary(),'rows'=>$this->repo->queue($status,$quality,$q,200),'status'=>$status,'quality'=>$quality,'query'=>$q]));}
 public function update(Request$r):Response{$this->repo->save($r->int('id'),$r->string('review_status'),$r->string('expected_item'),$r->string('expected_requirement')!==''?$r->int('expected_requirement'):null,$r->string('expected_attribute'),$r->string('expected_market_key'),$r->string('notes'));return new Response('',302,['Location'=>'/parser-review?status='.$r->string('return_status','pending')]);}
 public function export(Request$r):Response{return Response::json(['version'=>'v1.6','exported_at'=>date(DATE_ATOM),'cases'=>$this->repo->export()]);}
}
