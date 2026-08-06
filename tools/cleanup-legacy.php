<?php
declare(strict_types=1);
// CLI only. Run without --apply for a dry run.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__); $apply = in_array('--apply', $argv, true);
$patterns = ['PATCH-*.md','UPDATE*.md','diagnostics-v18.php','gwmarket-discover.php'];
$files=[]; foreach($patterns as$p) foreach(glob($root.'/'.$p)?:[] as$f) $files[]=$f;
foreach(array_unique($files) as$file){echo ($apply?'REMOVE ':'WOULD REMOVE ').substr($file,strlen($root)+1).PHP_EOL;if($apply)@unlink($file);} echo $apply?"Cleanup complete.\n":"Dry run only. Add --apply to remove these safe legacy files.\n";
