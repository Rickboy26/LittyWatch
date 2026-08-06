<?php
declare(strict_types=1);
namespace LittyWatch\Controllers; use LittyWatch\Core\Request;use LittyWatch\Core\Response;use LittyWatch\Core\View;use LittyWatch\Repositories\StructuredOfferRepository;
final class StructuredOfferController{public function __construct(private readonly StructuredOfferRepository $repo,private readonly View $view){}public function index(Request $request):Response{return Response::html($this->view->render('structured/index',['summary'=>$this->repo->summary(),'offers'=>$this->repo->latest(100),'markets'=>$this->repo->markets(100),'comparison'=>$this->repo->comparison(50)]));}}
