<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$writer = $root . '/app/Market/StructuredOfferWriter.php';
$target = $root . '/app/Market/Phase7E21AcceptedSafetyGuard.php';

if (!is_file($writer)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e21-fix1-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($writer, $backup . '/StructuredOfferWriter.php');
if (is_file($target)) {
    copy($target, $backup . '/Phase7E21AcceptedSafetyGuard.php');
}

$guard = base64_decode('PD9waHAKZGVjbGFyZShzdHJpY3RfdHlwZXM9MSk7CgpuYW1lc3BhY2UgTGl0dHlXYXRjaFxNYXJrZXQ7CgpmaW5hbCBjbGFzcyBQaGFzZTdFMjFBY2NlcHRlZFNhZmV0eUd1YXJkCnsKICAgIHB1YmxpYyBmdW5jdGlvbiByZXBhaXIoYXJyYXkgJHJvdyk6IGFycmF5CiAgICB7CiAgICAgICAgJGtleSA9IHN0cl9yZXBsYWNlKCdfJywnLScsbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2l0ZW1fa2V5J10gPz8gJycpKSkpOwogICAgICAgICRpdGVtID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2l0ZW0nXSA/PyAnJykpKTsKICAgICAgICAkc2VnID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ3Jhd19zZWdtZW50J10gPz8gJycpKSk7CiAgICAgICAgJG1zZyA9IG1iX3N0cnRvbG93ZXIoKHN0cmluZykoJHJvd1snX21lc3NhZ2UnXSA/PyAnJykpOwogICAgICAgICRhdHRyID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2F0dHJpYnV0ZV9rZXknXSA/PyAnJykpKTsKCiAgICAgICAgaWYgKHN0cl9jb250YWlucygka2V5LCAnc3RhZmYnKQogICAgICAgICAgICAmJiBwcmVnX21hdGNoKCcvXGJzY2VwdGVyXGIvaXUnLCAkc2VnKQogICAgICAgICAgICAmJiAhcHJlZ19tYXRjaCgnL1xic3RhZmZcYi9pdScsICRzZWcpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+cmVqZWN0KCRyb3csICdhY2NlcHRlZF93ZWFwb25fdHlwZV9jb2xsaXNpb24nLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIGlmIChwcmVnX21hdGNoKCcvXGJ3YW5kXGIvaXUnLCAkc2VnKQogICAgICAgICAgICAmJiAoCiAgICAgICAgICAgICAgICBzdHJfY29udGFpbnMoJGtleSwgJ2NoYWtyYW0nKQogICAgICAgICAgICAgICAgfHwgc3RyX2NvbnRhaW5zKCRpdGVtLCAnY2hha3JhbScpCiAgICAgICAgICAgICAgICB8fCBzdHJfY29udGFpbnMoJGtleSwgJ2ZvY3VzJykKICAgICAgICAgICAgICAgIHx8IHN0cl9jb250YWlucygkaXRlbSwgJ2ZvY3VzJykKICAgICAgICAgICAgKSkgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPnJlamVjdCgkcm93LCAnYWNjZXB0ZWRfd2VhcG9uX3R5cGVfY29sbGlzaW9uJywgMC4yMCk7CiAgICAgICAgfQoKICAgICAgICBpZiAoJGtleSA9PT0gJ29mLWVuY2hhbnRpbmcnCiAgICAgICAgICAgICYmIHByZWdfbWF0Y2goJy9cYjE1XHMqJVxzKig/OmVuY2h8ZW5jaGFudGVkKVxiL2l1JywgJHNlZykpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5yZWplY3QoJHJvdywgJ2FjY2VwdGVkX21vZGlmaWVyX2NvbGxpc2lvbicsIDAuMjApOwogICAgICAgIH0KCiAgICAgICAgaWYgKCRrZXkgPT09ICdibGVzc2luZy1vZi13YXInCiAgICAgICAgICAgICYmIHByZWdfbWF0Y2goJy9cYmJvd1xiL2l1JywgJHNlZykKICAgICAgICAgICAgJiYgIXByZWdfbWF0Y2goJy9cYmJsZXNzaW5nXHMrb2Zccyt3YXJcYnxcYmJvd1xiLipcYmJsZXNzaW5nXGIvaXUnLCAkc2VnKSkgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPnJlamVjdCgkcm93LCAnYWNjZXB0ZWRfbmFtZWRfaXRlbV9jb2xsaXNpb24nLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIGlmIChzdHJfY29udGFpbnMoJGtleSwgJ3J1bmUnKQogICAgICAgICAgICAmJiBwcmVnX21hdGNoKCcvXGJzaGllKD86bGQpP1xiL2l1JywgJHNlZyAuICcgJyAuICRtc2cpCiAgICAgICAgICAgICYmIHByZWdfbWF0Y2goJy9cYig/OnN0cmVuZ3RofGxlYWRlcnNoaXApXGIvaXUnLCAkc2VnIC4gJyAnIC4gJG1zZykpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5yZWplY3QoJHJvdywgJ2FjY2VwdGVkX2l0ZW1fZmFtaWx5X2NvbGxpc2lvbicsIDAuMjApOwogICAgICAgIH0KCiAgICAgICAgaWYgKGluX2FycmF5KCRrZXksIFsnZmllcnknLCdmaXJlJywnZmllcnktZHJhZyddLCB0cnVlKQogICAgICAgICAgICAmJiBwcmVnX21hdGNoKCcvXGJmaWVyeVxzK2RyYWcoPzpvbik/XHMrc3dvcmRcYi9pdScsICRzZWcgLiAnICcgLiAkbXNnKSkgewogICAgICAgICAgICAkcm93WydpdGVtJ10gPSAnRmllcnkgRHJhZ29uIFN3b3JkJzsKICAgICAgICAgICAgJHJvd1snaXRlbV9rZXknXSA9ICdmaWVyeS1kcmFnb24tc3dvcmQnOwogICAgICAgICAgICAkcm93WydtYXJrZXRfa2V5J10gPSAnZmllcnktZHJhZ29uLXN3b3JkJzsKICAgICAgICAgICAgJHJvd1sncXVhbGl0eV9zdGF0dXMnXSA9ICdhY2NlcHRlZCc7CiAgICAgICAgICAgICRyb3dbJ3F1YWxpdHlfcmVhc29uJ10gPSAnY2F0YWxvZ19tYXRjaCc7CiAgICAgICAgICAgICRyb3dbJ2NvbmZpZGVuY2UnXSA9IG1heCgoZmxvYXQpKCRyb3dbJ2NvbmZpZGVuY2UnXSA/PyAwKSwgMC45Nik7CiAgICAgICAgICAgIHJldHVybiAkcm93OwogICAgICAgIH0KCiAgICAgICAgaWYgKHN0cl9jb250YWlucygka2V5LCAnc2hpZWxkJykKICAgICAgICAgICAgJiYgaW5fYXJyYXkoJGF0dHIsIFsKICAgICAgICAgICAgICAgICdjb21tdW5pbmcnLCdjaGFubmVsaW5nX21hZ2ljJywncmVzdG9yYXRpb25fbWFnaWMnLCdzcGF3bmluZ19wb3dlcicsCiAgICAgICAgICAgICAgICAnZG9taW5hdGlvbl9tYWdpYycsJ2lsbHVzaW9uX21hZ2ljJywnZmFzdF9jYXN0aW5nJywnaW5zcGlyYXRpb25fbWFnaWMnLAogICAgICAgICAgICAgICAgJ2RlYXRoX21hZ2ljJywnY3Vyc2VzJywnc291bF9yZWFwaW5nJywnYmxvb2RfbWFnaWMnLAogICAgICAgICAgICAgICAgJ2ZpcmVfbWFnaWMnLCd3YXRlcl9tYWdpYycsJ2Fpcl9tYWdpYycsJ2VhcnRoX21hZ2ljJywnZW5lcmd5X3N0b3JhZ2UnLAogICAgICAgICAgICAgICAgJ2RpdmluZV9mYXZvcicsJ2hlYWxpbmdfcHJheWVycycsJ3Byb3RlY3Rpb25fcHJheWVycycsJ3NtaXRpbmdfcHJheWVycycKICAgICAgICAgICAgXSwgdHJ1ZSkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5yZWplY3QoJHJvdywgJ2FjY2VwdGVkX2ltcG9zc2libGVfdmFyaWFudCcsIDAuMjApOwogICAgICAgIH0KCiAgICAgICAgcmV0dXJuICRyb3c7CiAgICB9CgogICAgcHJpdmF0ZSBmdW5jdGlvbiByZWplY3QoYXJyYXkgJHJvdywgc3RyaW5nICRyZWFzb24sIGZsb2F0ICRjYXApOiBhcnJheQogICAgewogICAgICAgICRyb3dbJ3F1YWxpdHlfc3RhdHVzJ10gPSAncmVqZWN0ZWQnOwogICAgICAgICRyb3dbJ3F1YWxpdHlfcmVhc29uJ10gPSAkcmVhc29uOwogICAgICAgICRyb3dbJ2NvbmZpZGVuY2UnXSA9IG1pbigoZmxvYXQpKCRyb3dbJ2NvbmZpZGVuY2UnXSA/PyAwKSwgJGNhcCk7CiAgICAgICAgcmV0dXJuICRyb3c7CiAgICB9Cn0K');
if ($guard === false || file_put_contents($target, $guard) === false) {
    fwrite(STDERR, "ERROR: guard kon niet worden geschreven.\n");
    exit(1);
}

$code = file_get_contents($writer);
if ($code === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php kon niet worden gelezen.\n");
    exit(1);
}

if (!str_contains($code, 'LITTYWATCH_PHASE7E21_ACCEPTED_SAFETY')) {
    $needle = '$itemKeys[]=$r[\'item_key\'];$ins->execute';
    $p = strpos($code, $needle);

    if ($p === false) {
        $needle = '$ins->execute([';
        $p = strpos($code, $needle);
    }

    if ($p === false) {
        fwrite(STDERR, "ERROR: persistence anker niet gevonden; patch afgebroken.\n");
        exit(1);
    }

    $block = <<<'PHPBLOCK'
     // LITTYWATCH_PHASE7E21_ACCEPTED_SAFETY
     if(($r['quality_status']??'')==='accepted'){
       $r['_message']=(string)($message??'');
       $r=(new Phase7E21AcceptedSafetyGuard())->repair($r);
       unset($r['_message']);
     }
PHPBLOCK;

    $code = substr($code, 0, $p) . $block . "\n" . substr($code, $p);

    if (file_put_contents($writer, $code) === false) {
        fwrite(STDERR, "ERROR: writer kon niet worden bijgewerkt.\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.21 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E21AcceptedSafetyGuard.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e21-fix1/smoke-test.php\n";
