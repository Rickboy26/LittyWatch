<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");
    exit(1);
}

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: ParserEngine.php lezen mislukt.\n");
    exit(1);
}

$marker='LITTYWATCH_PHASE7E6_FIX3_DEDICATION_SHADOW_FILTER';
if(str_contains($code,$marker)){
    echo "Phase 7E.6 FIX3 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e6-fix3-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

$anchor='        $preItems = $this->itemMatcher->matchAll($segment);';

if(!str_contains($code,$anchor)){
    fwrite(STDERR,"ERROR: preItems anchor niet gevonden.\n");
    exit(1);
}

$replacement=<<<'PHP'
        $preItems = $this->itemMatcher->matchAll($segment);

        // LITTYWATCH_PHASE7E6_FIX3_DEDICATION_SHADOW_FILTER
        // The catalog may expose "ded"/"unded" as context aliases for the generic
        // Miniature umbrella. In a concrete miniature segment this creates a false
        // second item match:
        //
        //   Miniature Livia unded
        //     -> Miniature Livia
        //     -> Miniature (alias: Unded)
        //
        // That incorrectly forces parseSegment() into the multi-item/slice branch,
        // where the concrete miniature loses its dedication metadata. Remove only
        // this narrow generic shadow when a concrete miniature is already present.
        $hasConcreteMiniaturePreMatch = false;
        foreach ($preItems as $preItem) {
            $preName = mb_strtolower(trim((string)($preItem['item'] ?? '')));
            $preKey  = mb_strtolower(trim((string)($preItem['key'] ?? '')));
            if (
                $preName !== 'miniature'
                && (
                    str_starts_with($preName, 'miniature ')
                    || str_starts_with($preKey, 'miniature-')
                    || str_starts_with($preKey, 'miniature_')
                    || str_starts_with($preKey, 'mini-')
                    || str_starts_with($preKey, 'mini_')
                )
            ) {
                $hasConcreteMiniaturePreMatch = true;
                break;
            }
        }

        if ($hasConcreteMiniaturePreMatch && count($preItems) > 1) {
            $preItems = array_values(array_filter(
                $preItems,
                static function (array $preItem): bool {
                    $name  = mb_strtolower(trim((string)($preItem['item'] ?? '')));
                    $key   = mb_strtolower(trim((string)($preItem['key'] ?? '')));
                    $alias = mb_strtolower(trim((string)($preItem['alias'] ?? '')));

                    $genericMiniature = $name === 'miniature' || $key === 'miniature';
                    $dedicationAlias = preg_match(
                        '/^(?:uded|unded|undedi|undedicated|un[- ]?ded|ded|dedicated)$/u',
                        $alias
                    ) === 1;

                    return !($genericMiniature && $dedicationAlias);
                }
            ));
        }
PHP;

$code=str_replace($anchor,$replacement,$code,$count);

if($count!==1){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: onverwacht aantal preItems anchors: {$count}\n");
    exit(1);
}

if(file_put_contents($file,$code)===false){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

$out=[];$rc=0;
exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);

if($rc!==0){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.6 FIX3 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Ded/unded -> generieke Miniature shadow wordt verwijderd bij concrete miniature matches.\n";
echo "Normale generieke Miniature-marktvragen blijven ongemoeid.\n";
