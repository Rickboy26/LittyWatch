<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$catalog=$root.'/app/Parser/Catalog.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
$data=$root.'/app/Data/phase4f-items.json';

foreach([$catalog,$semantic,$data] as $f){
    if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");exit(1);}
}

$stamp=date('Ymd-His');
$backup=$root.'/storage/backups/phase4f-'.$stamp;
if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup)){fwrite(STDERR,"ERROR: backupmap\n");exit(1);}
foreach([$catalog,$semantic] as $f) copy($f,$backup.'/'.basename($f));

function one(string $s,string $n,string $r,string $label):string{
    $c=substr_count($s,$n);
    if($c!==1) throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$c}x.");
    return str_replace($n,$r,$s);
}

try{
    $c=(string)file_get_contents($catalog);
    if(!str_contains($c,'LITTYWATCH_PHASE4F_RESIDUAL_CATALOG')){
        $a="        \$this->modifiers = \$this->loadJson(\$dataDir . '/modifiers.json');\n";
        $r=
            "        // LITTYWATCH_PHASE4F_RESIDUAL_CATALOG\n".
            "        \$phase4fItemsPath = \$dataDir . '/phase4f-items.json';\n".
            "        if (is_file(\$phase4fItemsPath)) {\n".
            "            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4fItemsPath));\n".
            "        }\n".$a;
        $c=one($c,$a,$r,'Catalog modifiers');
        file_put_contents($catalog,$c);
    }

    $s=(string)file_get_contents($semantic);
    if(!str_contains($s,'LITTYWATCH_PHASE4F_RESIDUAL_NORMALIZATION')){
        $a="        // LITTYWATCH_PHASE4D_CANONICAL_FIXES_END\n";
        $rules=<<<'PHP'
        // LITTYWATCH_PHASE4F_RESIDUAL_NORMALIZATION
        $text = preg_replace('/\bLarge\s+(?:Equip(?:ment)?\s+Pack|eq\s*bag|eqbag)\b/iu','Large Equipment Pack',$text) ?? $text;
        $text = preg_replace('/\bHeavy\s+(?:Equip(?:ment)?\s+Pack|eq\s*bag|eqbag)\b/iu','Heavy Equipment Pack',$text) ?? $text;

        $text = preg_replace('/\b(?:stacks?\s+of\s+)?(?:Glittering\s+)?Dust\b/iu','Pile of Glittering Dust',$text) ?? $text;
        $text = preg_replace('/\b(?:stacks?\s+of\s+)?Cloth\b/iu','Bolt of Cloth',$text) ?? $text;
        $text = preg_replace('/\b(?:stacks?\s+of\s+)?Iron\b/iu','Iron Ingot',$text) ?? $text;
        $text = preg_replace('/\bStygian(?:\s+Gems?|\s+Gemstones?)?\b/iu','Stygian Gemstone',$text) ?? $text;

        $text = preg_replace('/\bPlagueborn\s+Shiled\b/iu','Plagueborn Shield',$text) ?? $text;

        $text = preg_replace('/\bbow\s*grip\s+of\s+necro(?:mancer)?\b|\bbowgrip\s+of\s+necro(?:mancer)?\b/iu','Bow Grip of the Necromancer',$text) ?? $text;
        $text = preg_replace('/\b(?:SR\s*\+\s*[45]\s+(?:for\s+)?bow|[45]\s+SR\s+for\s+bow)\b/iu','Bow Grip of the Necromancer',$text) ?? $text;

        $text = preg_replace('/\bReq[. ]*\s*(\d{1,2})\b/iu','q$1',$text) ?? $text;
        // LITTYWATCH_PHASE4F_RESIDUAL_NORMALIZATION_END

PHP;
        $s=one($s,$a,$rules.$a,'Phase 4D marker');
        file_put_contents($semantic,$s);
    }

    json_decode((string)file_get_contents($data),true,flags:JSON_THROW_ON_ERROR);

    foreach([$catalog,$semantic] as $f){
        $out=[];$code=0;exec('php -l '.escapeshellarg($f).' 2>&1',$out,$code);
        if($code!==0) throw new RuntimeException("Syntaxcheck faalde {$f}:\n".implode("\n",$out));
    }

    require $root.'/bootstrap.php';
    $db=db();
    new \LittyWatch\Parser\Catalog($root.'/app/Data',$db);

    echo "OK: LittyWatch V5.2 Phase 4F geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    echo "Catalog checks:\n";
    $q=$db->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
    foreach(['Large Equipment Pack','Heavy Equipment Pack','Pile of Glittering Dust','Bolt of Cloth','Iron Ingot','Stygian Gemstone','Plagueborn Shield','Bow Grip of the Necromancer',"Miniature Shiro'ken Assassin"] as $name){
        $q->execute([$name]);$k=$q->fetchColumn();
        echo "  {$name}: ".($k?"OK [{$k}]":"NIET GEVONDEN")."\n";
    }
    echo "\nDraai daarna de volledige reparse opnieuw.\n";
}catch(Throwable $e){
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\nRollback vanuit {$backup}...\n");
    foreach([$catalog,$semantic] as $f){
        $b=$backup.'/'.basename($f);if(is_file($b))copy($b,$f);
    }
    exit(1);
}
