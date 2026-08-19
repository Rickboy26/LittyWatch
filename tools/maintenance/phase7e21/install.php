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

$backup = $root . '/storage/backups/phase7e21-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($writer, $backup . '/StructuredOfferWriter.php');
if (is_file($target)) copy($target, $backup . '/Phase7E21AcceptedSafetyGuard.php');

$guard = base64_decode('PD9waHAKZGVjbGFyZShzdHJpY3RfdHlwZXM9MSk7CgpuYW1lc3BhY2UgTGl0dHlXYXRjaFxNYXJrZXQ7CgpmaW5hbCBjbGFzcyBQaGFzZTdFMjFBY2NlcHRlZFNhZmV0eUd1YXJkCnsKICAgIHB1YmxpYyBmdW5jdGlvbiByZXBhaXIoYXJyYXkgJHJvdyk6IGFycmF5CiAgICB7CiAgICAgICAgJGtleSA9IHN0cl9yZXBsYWNlKCdfJywnLScsbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2l0ZW1fa2V5J10gPz8gJycpKSkpOwogICAgICAgICRpdGVtID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2l0ZW0nXSA/PyAnJykpKTsKICAgICAgICAkc2VnID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ3Jhd19zZWdtZW50J10gPz8gJycpKSk7CiAgICAgICAgJG1zZyA9IG1iX3N0cnRvbG93ZXIoKHN0cmluZykoJHJvd1snX21lc3NhZ2UnXSA/PyAnJykpOwogICAgICAgICRhdHRyID0gbWJfc3RydG9sb3dlcih0cmltKChzdHJpbmcpKCRyb3dbJ2F0dHJpYnV0ZV9rZXknXSA/PyAnJykpKTsKCiAgICAgICAgaWYgKHN0cl9jb250YWlucygka2V5LCAnc3RhZmYnKSAmJiBwcmVnX21hdGNoKCcvXGJzY2VwdGVyXGIvaXUnLCAkc2VnKSAmJiAhcHJlZ19tYXRjaCgnL1xic3RhZmZcYi9pdScsICRzZWcpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+cmVqZWN0KCRyb3csICdhY2NlcHRlZF93ZWFwb25fdHlwZV9jb2xsaXNpb24nLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIGlmIChwcmVnX21hdGNoKCcvXGJ3YW5kXGIvaXUnLCAkc2VnKSAmJiAoCiAgICAgICAgICAgIHN0cl9jb250YWlucygka2V5LCAnY2hha3JhbScpIHx8IHN0cl9jb250YWlucygkaXRlbSwgJ2NoYWtyYW0nKQogICAgICAgICAgICB8fCBzdHJfY29udGFpbnMoJGtleSwgJ2ZvY3VzJykgfHwgc3RyX2NvbnRhaW5zKCRpdGVtLCAnZm9jdXMnKQogICAgICAgICkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5yZWplY3QoJHJvdywgJ2FjY2VwdGVkX3dlYXBvbl90eXBlX2NvbGxpc2lvbicsIDAuMjApOwogICAgICAgIH0KCiAgICAgICAgaWYgKCRrZXkgPT09ICdvZi1lbmNoYW50aW5nJyAmJiBwcmVnX21hdGNoKCcvXGIxNVxzKiVccyooPzplbmNofGVuY2hhbnRlZClcYi9pdScsICRzZWcpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+cmVqZWN0KCRyb3csICdhY2NlcHRlZF9tb2RpZmllcl9jb2xsaXNpb24nLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIGlmICgka2V5ID09PSAnYmxlc3Npbmctb2Ytd2FyJyAmJiBwcmVnX21hdGNoKCcvXGJib3dcYi9pdScsICRzZWcpCiAgICAgICAgICAgICYmICFwcmVnX21hdGNoKCcvXGJibGVzc2luZ1xzK29mXHMrd2FyXGJ8XGJib3dcYi4qXGJibGVzc2luZ1xiL2l1JywgJHNlZykpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5yZWplY3QoJHJvdywgJ2FjY2VwdGVkX25hbWVkX2l0ZW1fY29sbGlzaW9uJywgMC4yMCk7CiAgICAgICAgfQoKICAgICAgICBpZiAoc3RyX2NvbnRhaW5zKCRrZXksICdydW5lJykKICAgICAgICAgICAgJiYgcHJlZ19tYXRjaCgnL1xic2hpZSg/OmxkKT9cYi9pdScsICRzZWcgLiAnICcgLiAkbXNnKQogICAgICAgICAgICAmJiBwcmVnX21hdGNoKCcvXGIoPzpzdHJlbmd0aHxsZWFkZXJzaGlwKVxiL2l1JywgJHNlZyAuICcgJyAuICRtc2cpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+cmVqZWN0KCRyb3csICdhY2NlcHRlZF9pdGVtX2ZhbWlseV9jb2xsaXNpb24nLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIGlmIChpbl9hcnJheSgka2V5LCBbJ2ZpZXJ5JywnZmlyZScsJ2ZpZXJ5LWRyYWcnXSwgdHJ1ZSkKICAgICAgICAgICAgJiYgcHJlZ19tYXRjaCgnL1xiZmllcnlccytkcmFnKD86b24pP1xzK3N3b3JkXGIvaXUnLCAkc2VnIC4gJyAnIC4gJG1zZykpIHsKICAgICAgICAgICAgJHJvd1snaXRlbSddID0gJ0ZpZXJ5IERyYWdvbiBTd29yZCc7CiAgICAgICAgICAgICRyb3dbJ2l0ZW1fa2V5J10gPSAnZmllcnktZHJhZ29uLXN3b3JkJzsKICAgICAgICAgICAgJHJvd1snbWFya2V0X2tleSddID0gJ2ZpZXJ5LWRyYWdvbi1zd29yZCc7CiAgICAgICAgICAgICRyb3dbJ3F1YWxpdHlfc3RhdHVzJ10gPSAnYWNjZXB0ZWQnOwogICAgICAgICAgICAkcm93WydxdWFsaXR5X3JlYXNvbiddID0gJ2NhdGFsb2dfbWF0Y2gnOwogICAgICAgICAgICAkcm93Wydjb25maWRlbmNlJ10gPSBtYXgoKGZsb2F0KSgkcm93Wydjb25maWRlbmNlJ10gPz8gMCksIDAuOTYpOwogICAgICAgICAgICByZXR1cm4gJHJvdzsKICAgICAgICB9CgogICAgICAgIGlmIChzdHJfY29udGFpbnMoJGtleSwgJ3NoaWVsZCcpICYmIGluX2FycmF5KCRhdHRyLCBbCiAgICAgICAgICAgICdjb21tdW5pbmcnLCdjaGFubmVsaW5nX21hZ2ljJywncmVzdG9yYXRpb25fbWFnaWMnLCdzcGF3bmluZ19wb3dlcicsCiAgICAgICAgICAgICdkb21pbmF0aW9uX21hZ2ljJywnaWxsdXNpb25fbWFnaWMnLCdmYXN0X2Nhc3RpbmcnLCdpbnNwaXJhdGlvbl9tYWdpYycsCiAgICAgICAgICAgICdkZWF0aF9tYWdpYycsJ2N1cnNlcycsJ3NvdWxfcmVhcGluZycsJ2Jsb29kX21hZ2ljJywKICAgICAgICAgICAgJ2ZpcmVfbWFnaWMnLCd3YXRlcl9tYWdpYycsJ2Fpcl9tYWdpYycsJ2VhcnRoX21hZ2ljJywnZW5lcmd5X3N0b3JhZ2UnLAogICAgICAgICAgICAnZGl2aW5lX2Zhdm9yJywnaGVhbGluZ19wcmF5ZXJzJywncHJvdGVjdGlvbl9wcmF5ZXJzJywnc21pdGluZ19wcmF5ZXJzJwogICAgICAgIF0sIHRydWUpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+cmVqZWN0KCRyb3csICdhY2NlcHRlZF9pbXBvc3NpYmxlX3ZhcmlhbnQnLCAwLjIwKTsKICAgICAgICB9CgogICAgICAgIHJldHVybiAkcm93OwogICAgfQoKICAgIHByaXZhdGUgZnVuY3Rpb24gcmVqZWN0KGFycmF5ICRyb3csIHN0cmluZyAkcmVhc29uLCBmbG9hdCAkY2FwKTogYXJyYXkKICAgIHsKICAgICAgICAkcm93WydxdWFsaXR5X3N0YXR1cyddID0gJ3JlamVjdGVkJzsKICAgICAgICAkcm93WydxdWFsaXR5X3JlYXNvbiddID0gJHJlYXNvbjsKICAgICAgICAkcm93Wydjb25maWRlbmNlJ10gPSBtaW4oKGZsb2F0KSgkcm93Wydjb25maWRlbmNlJ10gPz8gMCksICRjYXApOwogICAgICAgIHJldHVybiAkcm93OwogICAgfQp9Cg==');
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
    $needle = "$itemKeys[]=$r['item_key'];$ins->execute";
    $p = strpos($code, $needle);
    if ($p === false) {
        $needle = "$ins->execute([";
        $p = strpos($code, $needle);
    }
    if ($p === false) {
        fwrite(STDERR, "ERROR: persistence anker niet gevonden; patch afgebroken.\n");
        exit(1);
    }

    $block =
        "     // LITTYWATCH_PHASE7E21_ACCEPTED_SAFETY\n".
        "     if((\$r['quality_status']??'')==='accepted'){\n".
        "       \$r['_message']=(string)(\$message??'');\n".
        "       \$r=(new Phase7E21AcceptedSafetyGuard())->repair(\$r);\n".
        "       unset(\$r['_message']);\n".
        "     }\n";

    $code = substr($code, 0, $p) . $block . substr($code, $p);

    if (file_put_contents($writer, $code) === false) {
        fwrite(STDERR, "ERROR: writer kon niet worden bijgewerkt.\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.21 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E21AcceptedSafetyGuard.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e21/smoke-test.php\n";
