<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$catalog=new Catalog($root.'/app/Data',db());
$engine=new ParserEngine($catalog);

$tests=[
 ['WTS Unded Kuuna 250a','Miniature Kuunavang','accepted','catalog_match'],
 ['WTS Ded Kuuna 20a','Miniature Kuunavang','accepted','catalog_match'],
 ['WTS Kuuna 250a','Miniature Kuunavang','review','miniature_variant_unresolved'],
 ['WTS Unded Rift Warden 25a','Miniature Rift Warden','accepted','catalog_match'],
 ['WTS Rift Warden 25a','Miniature Rift Warden','review','miniature_variant_unresolved'],
 ['WTS unded mini dhuum 45a','Miniature Dhuum','accepted','catalog_match'],
 ['WTS mini dhuum 45a','Miniature Dhuum','review','miniature_variant_unresolved'],
];

echo "Phase 7E.2 FIX4 parser pre-check\n";
$fail=0;
foreach($tests as [$text,$item,$status,$reason]){
 $found=null;
 foreach($engine->parse($text) as $o){if($o->item===$item){$found=$o;break;}}
 if(!$found){echo "$text => FAIL niet gevonden\n";$fail++;continue;}
 printf("%-30s => %-26s status=%-8s reason=%-30s %s\n",
   $text,$found->item,$found->status,$found->reason,
   ($found->status===$status&&$found->reason===$reason)?'OK':'CHECK');
}
echo "\nLET OP: finale writer-invariant wordt na reparse geverifieerd met verify-after-reparse.php.\n";
