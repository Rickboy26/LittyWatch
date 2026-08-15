<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Market/StructuredOfferWriter.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: lezen mislukt.\n");
    exit(1);
}

$marker='LITTYWATCH_PHASE7E2_FIX4_WRITER_MINIATURE_VARIANT_INVARIANT';
if(str_contains($code,$marker)){
    echo "Phase 7E.2 FIX4 staat al in StructuredOfferWriter.php.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e2-fix4a-'.date('Ymd-His');
@mkdir($backup,0775,true);
if(!copy($file,$backup.'/StructuredOfferWriter.php')){
    fwrite(STDERR,"ERROR: backup mislukt.\n");
    exit(1);
}

/*
 * Robust patch:
 * match the actual foreach($resolved as $r){ followed by the accepted gate,
 * independent of indentation/line endings.
 */
$pattern='/foreach\s*\(\s*\$resolved\s+as\s+\$r\s*\)\s*\{\s*if\s*\(\s*\$r\[\'quality_status\'\]\s*===\s*\'accepted\'\s*\)\s*\{/u';

$replacement=<<<'PHP'
foreach($resolved as $r){
    // LITTYWATCH_PHASE7E2_FIX4_WRITER_MINIATURE_VARIANT_INVARIANT
    // Final persistence invariant for concrete miniatures.
    $r=$this->reconcileMiniatureVariant($r);

    if($r['quality_status']==='accepted'){
PHP;

$patched=preg_replace($pattern,$replacement,$code,1,$count);
if($patched===null || $count!==1){
    copy($backup.'/StructuredOfferWriter.php',$file);
    fwrite(STDERR,"ERROR: writer-loop kon niet uniek worden gepatcht (matches={$count}).\n");
    exit(1);
}
$code=$patched;

$anchor='   private function map(ParsedOffer $o):array{';
if(!str_contains($code,$anchor)){
    // tolerate different indentation
    $anchor='private function map(ParsedOffer $o):array{';
}

$method=<<<'PHP'

   /** @param array<string,mixed> $row @return array<string,mixed> */
   private function reconcileMiniatureVariant(array $row):array{
    $item=mb_strtolower(trim((string)($row['item']??'')));
    if(!str_starts_with($item,'miniature '))return $row;

    $relevant=$this->decodeJsonArray($row['relevant_json']??null);
    $mods=$this->decodeJsonArray($row['mods_json']??null);
    $ded=$relevant['dedication']??$mods['dedication']??null;

    if(!is_string($ded)||!in_array(mb_strtolower($ded),['dedicated','undedicated'],true)){
     $ded=null;
     foreach([(string)($row['normalized_market_key']??''),(string)($row['market_key']??'')] as $mk){
      if(preg_match('/(?:^|\|)dedication:(dedicated|undedicated)(?:\||$)/iu',$mk,$m)){
       $ded=mb_strtolower((string)$m[1]);
       break;
      }
     }
    }else{
     $ded=mb_strtolower($ded);
    }

    if($ded!==null){
     $relevant['dedication']=$ded;
     $row['relevant_json']=$this->json($relevant);

     if((string)($row['quality_reason']??'')==='miniature_variant_unresolved'){
      $row['quality_status']='accepted';
      $row['quality_reason']='catalog_match';
      $row['confidence']=max(0.90,(float)($row['confidence']??0));
     }

     return $row;
    }

    if((string)($row['quality_status']??'')==='accepted'
      || in_array((string)($row['quality_reason']??''),['catalog_match','low_confidence','miniature_variant_unresolved'],true)){
     $row['quality_status']='review';
     $row['quality_reason']='miniature_variant_unresolved';
    }

    return $row;
   }

PHP;

$pos=strpos($code,$anchor);
if($pos===false){
    copy($backup.'/StructuredOfferWriter.php',$file);
    fwrite(STDERR,"ERROR: map()-anchor niet gevonden.\n");
    exit(1);
}

$code=substr($code,0,$pos).$method.substr($code,$pos);

if(file_put_contents($file,$code)===false){
    copy($backup.'/StructuredOfferWriter.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/StructuredOfferWriter.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n");
    fwrite(STDERR,implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.2 FIX4a geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Writer-level miniature invariant actief.\n";
echo "Draai nu:\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e2-fix4a/check-install.php\n";
