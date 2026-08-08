<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Market\StrictCatalogGate;
use LittyWatch\Knowledge\Schema;
$pdo=db();Schema::install($pdo);
$pdo->exec("DELETE FROM kb_aliases; DELETE FROM kb_items;");
$kb=new LittyWatch\Knowledge\KnowledgeBase($pdo);
$kb->upsertItem('miniature-ghostly-priest','Miniature Ghostly Priest','miniature','test');
$kb->addAlias('miniature-ghostly-priest','unded Gpriest','test');
$g=new StrictCatalogGate($pdo);
$cases=[
 ['Miniature','miniature',false],
 ['Faster casting of REPLACE','faster-casting-of-replace',false],
 ['Any Rare FlatBow','any-rare-flatbow',false],
 ['Miniature Ghostly Priest','miniature-ghostly-priest',true],
 ['unded Gpriest','unded-gpriest',true],
 ['Made Up Sword','made-up-sword',false],
];
foreach($cases as[$name,$key,$expected]){
 $got=$g->inspect($name,$key)['allowed'];
 if($got!==$expected){fwrite(STDERR,"FAIL $name\n");exit(1);}
}
echo "OK strict catalog gate\n";
