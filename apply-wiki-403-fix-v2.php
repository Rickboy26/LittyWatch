<?php
declare(strict_types=1);

/**
 * LittyWatch Wiki HTTP 403 hotfix v2
 *
 * Deze versie slaat back-ups op in de tijdelijke servermap wanneer de
 * applicatiemap geen nieuwe bestanden toestaat.
 */

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$wikiClient = $root . '/app/V2/Encyclopedia/WikiClient.php';
$catalogPage = $root . '/v2-catalog-import.php';

function fail(string $message): never
{
    http_response_code(500);
    exit("FOUT: {$message}\n");
}

function makeBackup(string $path): string
{
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        fail("Bestand kon niet worden gelezen voor back-up: {$path}");
    }

    $tmpDir = sys_get_temp_dir();
    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($path)) ?: 'backup.php';
    $backup = rtrim($tmpDir, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'littywatch-' . date('Ymd-His') . '-' . $safeName . '.bak';

    if (file_put_contents($backup, $contents, LOCK_EX) === false) {
        fail("Back-up kon ook niet in de tijdelijke map worden opgeslagen: {$backup}");
    }

    return $backup;
}

function writePatchedFile(string $path, string $contents): void
{
    if (!is_writable($path)) {
        fail(
            "PHP mag {$path} niet wijzigen. Pas tijdelijk de eigenaar/rechten aan "
            . "of upload het aangepaste bestand handmatig."
        );
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        fail("Aangepast bestand kon niet worden opgeslagen: {$path}");
    }
}

if (!is_file($wikiClient)) {
    fail('WikiClient.php niet gevonden. Plaats dit bestand in de hoofdmap van LittyWatch.');
}

$source = file_get_contents($wikiClient);

if (!is_string($source) || $source === '') {
    fail('WikiClient.php kon niet worden gelezen.');
}

if (str_contains($source, 'LITTYWATCH_WIKI_403_HOTFIX')) {
    echo "De Wiki 403-hotfix is al toegepast.\n";
    exit;
}

$oldStart = '    private function request(string $url, string $accept): string';
$start = strpos($source, $oldStart);

if ($start === false) {
    fail('De request()-methode is niet gevonden. Mogelijk gebruik je een andere versie.');
}

$braceStart = strpos($source, '{', $start);

if ($braceStart === false) {
    fail('Openingsaccolade van request() niet gevonden.');
}

$depth = 0;
$end = null;
$length = strlen($source);

for ($i = $braceStart; $i < $length; $i++) {
    if ($source[$i] === '{') {
        $depth++;
    } elseif ($source[$i] === '}') {
        $depth--;

        if ($depth === 0) {
            $end = $i + 1;
            break;
        }
    }
}

if ($end === null) {
    fail('Einde van request() niet gevonden.');
}

$newMethod = <<<'PHP'
    /**
     * LITTYWATCH_WIKI_403_HOTFIX
     */
    private function request(string $url, string $accept): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL ontbreekt op de server.');
        }

        static $lastRequestAt = 0.0;

        $minimumDelaySeconds = 0.85;
        $elapsed = microtime(true) - $lastRequestAt;

        if ($lastRequestAt > 0 && $elapsed < $minimumDelaySeconds) {
            usleep((int)(($minimumDelaySeconds - $elapsed) * 1_000_000));
        }

        $cookieFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'littywatch-guildwars-wiki.cookies';

        $attempts = 3;
        $lastStatus = 0;
        $lastError = '';
        $lastBody = '';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $ch = curl_init($url);

            if ($ch === false) {
                throw new RuntimeException('cURL kon niet worden gestart.');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERAGENT =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                    . 'Chrome/126.0.0.0 Safari/537.36',
                CURLOPT_REFERER => self::BASE . '/wiki/Main_Page',
                CURLOPT_COOKIEJAR => $cookieFile,
                CURLOPT_COOKIEFILE => $cookieFile,
                CURLOPT_HTTPHEADER => [
                    'Accept: ' . $accept,
                    'Accept-Language: en-US,en;q=0.9,nl;q=0.8',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                    'DNT: 1',
                    'X-LittyWatch-Client: LittyWatch Wiki Catalog/2.7.2',
                ],
            ]);

            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);

            curl_close($ch);
            $lastRequestAt = microtime(true);

            $lastStatus = $status;
            $lastError = $error;
            $lastBody = is_string($body) ? $body : '';

            if (is_string($body) && $status >= 200 && $status < 300) {
                return $body;
            }

            $retryable = in_array(
                $status,
                [0, 403, 408, 425, 429, 500, 502, 503, 504],
                true
            );

            if (!$retryable || $attempt === $attempts) {
                break;
            }

            usleep($attempt * 1_500_000);
        }

        $details = 'HTTP ' . $lastStatus;

        if ($lastError !== '') {
            $details .= ' · ' . $lastError;
        }

        if ($lastStatus === 403) {
            $details .= ' · Guild Wars Wiki weigert het IP-adres of de request van deze webserver.';
        } elseif ($lastStatus === 429) {
            $details .= ' · Te veel aanvragen; probeer minder categorieën of pagina’s tegelijk.';
        }

        if ($lastBody !== '') {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($lastBody)) ?? '');

            if ($plain !== '') {
                $details .= ' · Antwoord: ' . mb_substr($plain, 0, 220);
            }
        }

        throw new RuntimeException($details);
    }
PHP;

$patched = substr($source, 0, $start)
    . $newMethod
    . substr($source, $end);

$wikiBackup = makeBackup($wikiClient);
writePatchedFile($wikiClient, $patched);

echo "OK: WikiClient.php aangepast.\n";
echo "Back-up opgeslagen in: {$wikiBackup}\n";

if (is_file($catalogPage)) {
    $catalog = file_get_contents($catalogPage);

    if (is_string($catalog) && $catalog !== '') {
        $originalInput = '<input name="category" value="Category:Items" placeholder="Category:Items">';
        $replacementInput = '<input name="category" value="<?= h((string)($_POST[\'category\'] ?? \'Category:Items\')) ?>" placeholder="Category:Items">';

        if (str_contains($catalog, $originalInput)) {
            $catalogBackup = makeBackup($catalogPage);
            $catalog = str_replace($originalInput, $replacementInput, $catalog);
            writePatchedFile($catalogPage, $catalog);

            echo "OK: gekozen categorie blijft in het formulier staan.\n";
            echo "Back-up opgeslagen in: {$catalogBackup}\n";
        } else {
            echo "INFO: formulierregel niet aangepast; mogelijk was deze al gewijzigd.\n";
        }
    }
}

echo "\nKLAAR.\n";
echo "Verwijder dit patchbestand nu van je server.\n";
echo "Test daarna Category:Miniatures met diepte 0 en maximaal 1 pagina.\n";
