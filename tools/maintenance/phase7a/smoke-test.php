<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ItemMatcher;

$root=dirname(__DIR__,3);

$ref=new ReflectionClass(Catalog::class);
$params=$ref->getConstructor()?->getParameters()??[];
$args=[];

foreach($params as $i=>$p){
    $t=$p->getType();
    $name=$t instanceof ReflectionNamedType?$t->getName():'';

    if($i===0 && $name==='string'){
        $args[]=$root.'/app/Data';
        continue;
    }

    if($name==='PDO'){
        $args[]=db();
        continue;
    }

    if($p->isDefaultValueAvailable()){
        $args[]=$p->getDefaultValue();
        continue;
    }

    fwrite(STDERR,"ERROR: onbekende Catalog constructor parameter: ".$p->getName()." type=".$name."\n");
    exit(1);
}

$catalog=$ref->newInstanceArgs($args);
echo "Catalog constructor OK.\n";

$items=$catalog->items();
$byKey=[];

foreach($items as $it){
    $k=(string)($it['key']??'');
    if($k!=='')$byKey[$k]=$it;
}

$failed=0;
$checked=0;

foreach(db()->query("
    SELECT alias,item_key
    FROM parser_learned_aliases
    WHERE active=1 AND confidence>=0.99
    ORDER BY id
") as $r){
    $key=(string)$r['item_key'];
    $alias=(string)$r['alias'];

    $aliases=$byKey[$key]['aliases']??[];
    $ok=isset($byKey[$key]) && is_array($aliases) && in_array($alias,$aliases,true);

    printf("%-30s -> [%s] %s\n",$alias,$key,$ok?'OK':'MISSING');

    $checked++;
    if(!$ok)$failed++;
}

echo "Learned aliases checked: {$checked}\n";

$matcher=new ItemMatcher($catalog);
echo "ItemMatcher constructor OK.\n";

$tests=[
    ['OBSI EDGE q11 8a','Obsidian Edge'],
    ['Outcast Dom 20a','Outcast Staff'],
    ['Plag Illus 20a','Plagueborn Staff'],
    ['Jade Sp 20a','Jade Staff'],
];

foreach($tests as [$text,$expected]){
    $names=array_map(
        static fn(array $m):string=>(string)($m['item']??''),
        $matcher->matchAll($text)
    );

    $ok=in_array($expected,$names,true);

    printf("%-25s -> %-24s %s\n",$text,$expected,$ok?'OK':'FAIL');

    if(!$ok)$failed++;
}

if($failed){
    fwrite(STDERR,"Smoke test FAILED: {$failed}\n");
    exit(1);
}

echo "Smoke test OK.\n";
