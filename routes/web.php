<?php
declare(strict_types=1);
use LittyWatch\Controllers\{AdminController,AlertController,AssetController,DashboardController,IntelligenceController,ItemController,KnowledgeController,KnowledgePackController,LiveController,MaintenanceController,ParserReviewController,StructuredMarketController,StructuredOfferController,SystemController,TraderController,TrendController,WatchlistController};
use LittyWatch\Core\{Container,Router,Response};
return static function(Router $router,Container $c):void{
 $router->get('/',fn($r)=>$c->get(DashboardController::class)->index($r));
 $router->get('/live',fn($r)=>$c->get(LiveController::class)->index($r));
 $router->get('/markets',fn($r)=>$c->get(StructuredMarketController::class)->index($r));$router->get('/market',fn($r)=>$c->get(StructuredMarketController::class)->show($r));
 $router->get('/items',fn($r)=>$c->get(ItemController::class)->index($r));$router->get('/item',fn($r)=>$c->get(ItemController::class)->show($r));
 $router->get('/traders',fn($r)=>$c->get(TraderController::class)->index($r));$router->get('/trader',fn($r)=>$c->get(TraderController::class)->show($r));
 $router->get('/trends',fn($r)=>$c->get(TrendController::class)->index($r));$router->get('/intelligence',fn($r)=>$c->get(IntelligenceController::class)->index($r));
 $router->get('/watchlist',fn($r)=>new Response('',302,['Location'=>'/alerts']));$router->post('/watchlist',fn($r)=>$c->get(WatchlistController::class)->update($r));
 $router->get('/alerts',fn($r)=>$c->get(AlertController::class)->index($r));$router->post('/alerts',fn($r)=>$c->get(AlertController::class)->update($r));
 $router->get('/game-assets',fn($r)=>$c->get(AssetController::class)->index($r));$router->post('/game-assets',fn($r)=>$c->get(AssetController::class)->update($r));$router->post('/game-assets/auto-link',fn($r)=>$c->get(AssetController::class)->autoLink($r));$router->post('/admin/assets-knowledge-cleanup',fn($r)=>$c->get(AssetController::class)->cleanupKnowledge($r));$router->post('/admin/assets-gwca-ids',fn($r)=>$c->get(AssetController::class)->importGwcaIds($r));$router->post('/admin/assets-runtime-ids',fn($r)=>$c->get(AssetController::class)->importRuntimeIds($r));$router->post('/admin/assets-named-import',fn($r)=>$c->get(AssetController::class)->importNamedAsset($r));$router->get('/admin/assets-named-catalog',fn($r)=>$c->get(AssetController::class)->namedImportCatalog($r));$router->get('/game-assets/wiki-icon',fn($r)=>$c->get(AssetController::class)->wikiIcon($r));$router->get('/system',fn($r)=>$c->get(SystemController::class)->index($r));
 $router->get('/structured-offers',fn($r)=>$c->get(StructuredOfferController::class)->index($r));$router->get('/knowledge',fn($r)=>$c->get(KnowledgeController::class)->index($r));$router->post('/knowledge/gw-market-import',fn($r)=>$c->get(KnowledgeController::class)->importGwMarket($r));
 $router->get('/knowledge-pack',fn($r)=>$c->get(KnowledgePackController::class)->index($r));
 $router->post('/knowledge-pack/stage',fn($r)=>$c->get(KnowledgePackController::class)->stage($r));
 $router->post('/knowledge-pack/compile',fn($r)=>$c->get(KnowledgePackController::class)->compile($r));
 $router->post('/knowledge-pack/clear',fn($r)=>$c->get(KnowledgePackController::class)->clear($r));
 $router->get('/parser-review',fn($r)=>$c->get(ParserReviewController::class)->index($r));$router->post('/parser-review',fn($r)=>$c->get(ParserReviewController::class)->update($r));$router->post('/parser-review/re-evaluate',fn($r)=>$c->get(ParserReviewController::class)->batchReview($r));$router->get('/parser-review/export',fn($r)=>$c->get(ParserReviewController::class)->export($r));
 $router->get('/admin',fn($r)=>$c->get(AdminController::class)->index($r));
 $router->get('/admin/data-quality',fn($r)=>$c->get(AdminController::class)->dataQuality($r));
 $router->get('/admin/dataset',fn($r)=>$c->get(AdminController::class)->dataset($r));
 $router->get('/admin/dataset/export',fn($r)=>$c->get(AdminController::class)->datasetExport($r));
 foreach(['collect'=>'collect','reparse'=>'reparse','market-maintenance'=>'marketMaintenance','knowledge-seed'=>'seedKnowledge','intelligence-refresh'=>'intelligence','snapshot'=>'snapshot','assets-scan'=>'scanAssets','parser-lab'=>'parserLab'] as$path=>$method){$router->get('/admin/'.$path,fn($r)=>$c->get(MaintenanceController::class)->$method($r));}
 $router->post('/admin/parser-lab',fn($r)=>$c->get(MaintenanceController::class)->parserLab($r));
};
