<?php

declare(strict_types=1);

namespace LittyWatch\V2\Encyclopedia;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class WikiClient
{
    private const BASE = 'https://wiki.guildwars.com';
    private const USER_AGENT = 'LittyWatch/2.7.1 (Guild Wars market catalog; contact via LittyWatch website)';

    /**
     * Fetch a single Wiki page. API is attempted first; normal HTML is the
     * fallback for hosts that return HTTP 403 on api.php.
     *
     * @return array<string,mixed>
     */
    public function page(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException('Lege Wiki-titel.');
        }

        $apiError = null;
        try {
            return $this->pageViaApi($title);
        } catch (\Throwable $e) {
            $apiError = $e->getMessage();
        }

        try {
            $result = $this->pageViaHtml($title);
            $result['transport'] = 'html_fallback';
            $result['api_error'] = $apiError;
            return $result;
        } catch (\Throwable $htmlError) {
            throw new RuntimeException(
                'Wiki-opvraag mislukt. API: ' . $apiError . ' | HTML: ' . $htmlError->getMessage()
            );
        }
    }

    /**
     * Return category members and subcategories. The API uses continuation;
     * HTML fallback follows the "next page" link.
     *
     * @return array{pages:array<int,array<string,string>>,subcategories:array<int,array<string,string>>,transport:string}
     */
    public function category(string $categoryTitle, int $maxPages = 10): array
    {
        $categoryTitle = trim($categoryTitle);
        if ($categoryTitle === '') {
            throw new RuntimeException('Lege categorienaam.');
        }
        if (!str_starts_with(mb_strtolower($categoryTitle), 'category:')) {
            $categoryTitle = 'Category:' . $categoryTitle;
        }

        try {
            return $this->categoryViaApi($categoryTitle, $maxPages);
        } catch (\Throwable $apiError) {
            $result = $this->categoryViaHtml($categoryTitle, $maxPages);
            $result['transport'] = 'html_fallback';
            $result['api_error'] = $apiError->getMessage();
            return $result;
        }
    }

    /** @return array<string,mixed> */
    private function pageViaApi(string $title): array
    {
        $url = self::BASE . '/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'redirects' => '1',
            'prop' => 'extracts|pageimages|info|categories',
            'exintro' => '1',
            'explaintext' => '1',
            'piprop' => 'original',
            'inprop' => 'url',
            'cllimit' => 'max',
            'titles' => $title,
        ]);

        $body = $this->request($url, 'application/json,text/plain,*/*');
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $page = $data['query']['pages'][0] ?? null;

        if (!is_array($page) || isset($page['missing'])) {
            throw new RuntimeException('Wiki-pagina niet gevonden.');
        }

        return [
            'title' => (string)($page['title'] ?? $title),
            'description' => trim((string)($page['extract'] ?? '')),
            'image_url' => (string)($page['original']['source'] ?? ''),
            'source_url' => (string)($page['fullurl'] ?? ''),
            'categories' => array_values(array_map(
                static fn(array $category): string => (string)($category['title'] ?? ''),
                is_array($page['categories'] ?? null) ? $page['categories'] : []
            )),
            'transport' => 'api',
        ];
    }

    /** @return array<string,mixed> */
    private function pageViaHtml(string $title): array
    {
        $url = self::BASE . '/wiki/' . rawurlencode(str_replace(' ', '_', $title));
        $html = $this->request($url, 'text/html,application/xhtml+xml');

        [$dom, $xpath] = $this->dom($html);

        $heading = trim((string)$xpath->evaluate('string(//*[@id="firstHeading"])'));
        if ($heading === '') {
            throw new RuntimeException('Geen geldige Wiki-pagina ontvangen.');
        }

        $paragraphs = [];
        foreach ($xpath->query('//*[@id="mw-content-text"]//div[contains(@class,"mw-parser-output")]/p') ?: [] as $paragraph) {
            $text = trim(preg_replace('/\s+/u', ' ', (string)$paragraph->textContent) ?? '');
            if ($text !== '') {
                $paragraphs[] = $text;
            }
            if (count($paragraphs) >= 3) {
                break;
            }
        }

        $imageUrl = '';
        $imageNode = $xpath->query(
            '//*[@id="mw-content-text"]//*[contains(@class,"infobox")]//img | ' .
            '//*[@id="mw-content-text"]//a[contains(@class,"image")]//img'
        )?->item(0);
        if ($imageNode) {
            $imageUrl = $this->absoluteUrl((string)$imageNode->getAttribute('src'));
        }

        $categories = [];
        foreach ($xpath->query('//*[@id="mw-normal-catlinks"]//a[position()>1]') ?: [] as $node) {
            $categories[] = 'Category:' . trim((string)$node->textContent);
        }

        return [
            'title' => $heading,
            'description' => trim(implode("\n\n", $paragraphs)),
            'image_url' => $imageUrl,
            'source_url' => $url,
            'categories' => array_values(array_unique($categories)),
            'transport' => 'html',
        ];
    }

    /** @return array{pages:array<int,array<string,string>>,subcategories:array<int,array<string,string>>,transport:string} */
    private function categoryViaApi(string $categoryTitle, int $maxPages): array
    {
        $pages = [];
        $subcategories = [];
        $continue = null;
        $iterations = 0;

        do {
            $parameters = [
                'action' => 'query',
                'format' => 'json',
                'formatversion' => '2',
                'list' => 'categorymembers',
                'cmtitle' => $categoryTitle,
                'cmlimit' => '500',
                'cmprop' => 'ids|title|type',
                'cmsort' => 'sortkey',
                'cmdir' => 'asc',
            ];
            if ($continue !== null) {
                $parameters['cmcontinue'] = $continue;
            }

            $body = $this->request(
                self::BASE . '/api.php?' . http_build_query($parameters),
                'application/json,text/plain,*/*'
            );
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $members = $data['query']['categorymembers'] ?? [];

            foreach (is_array($members) ? $members : [] as $member) {
                $entry = [
                    'title' => (string)($member['title'] ?? ''),
                    'type' => (string)($member['type'] ?? 'page'),
                ];
                if ($entry['title'] === '') {
                    continue;
                }
                if ($entry['type'] === 'subcat') {
                    $subcategories[$entry['title']] = $entry;
                } elseif ($entry['type'] === 'page') {
                    $pages[$entry['title']] = $entry;
                }
            }

            $continue = $data['continue']['cmcontinue'] ?? null;
            $iterations++;
        } while ($continue !== null && $iterations < max(1, $maxPages));

        return [
            'pages' => array_values($pages),
            'subcategories' => array_values($subcategories),
            'transport' => 'api',
        ];
    }

    /** @return array{pages:array<int,array<string,string>>,subcategories:array<int,array<string,string>>,transport:string} */
    private function categoryViaHtml(string $categoryTitle, int $maxPages): array
    {
        $pages = [];
        $subcategories = [];
        $nextUrl = self::BASE . '/wiki/' . rawurlencode(str_replace(' ', '_', $categoryTitle));
        $visited = [];
        $iterations = 0;

        while ($nextUrl !== null && $iterations < max(1, $maxPages)) {
            if (isset($visited[$nextUrl])) {
                break;
            }
            $visited[$nextUrl] = true;

            $html = $this->request($nextUrl, 'text/html,application/xhtml+xml');
            [, $xpath] = $this->dom($html);

            foreach ($xpath->query('//*[@id="mw-subcategories"]//div[contains(@class,"CategoryTreeItem")]//a | //*[@id="mw-subcategories"]//li//a') ?: [] as $node) {
                $title = trim((string)$node->getAttribute('title'));
                if ($title === '') {
                    $title = 'Category:' . trim((string)$node->textContent);
                }
                if ($title !== '' && str_starts_with($title, 'Category:')) {
                    $subcategories[$title] = ['title' => $title, 'type' => 'subcat'];
                }
            }

            foreach ($xpath->query('//*[@id="mw-pages"]//div[contains(@class,"mw-category")]//a | //*[@id="mw-pages"]//div[contains(@class,"mw-category-generated")]//a') ?: [] as $node) {
                $title = trim((string)$node->getAttribute('title'));
                if ($title === '') {
                    $title = trim((string)$node->textContent);
                }
                if ($title !== '' && !str_starts_with($title, 'Category:')) {
                    $pages[$title] = ['title' => $title, 'type' => 'page'];
                }
            }

            $nextUrl = null;
            foreach ($xpath->query('//*[@id="mw-pages"]//a') ?: [] as $node) {
                if (mb_strtolower(trim((string)$node->textContent)) === 'next page') {
                    $nextUrl = $this->absoluteUrl((string)$node->getAttribute('href'));
                    break;
                }
            }

            $iterations++;
        }

        return [
            'pages' => array_values($pages),
            'subcategories' => array_values($subcategories),
            'transport' => 'html',
        ];
    }

    private function request(string $url, string $accept): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL ontbreekt op de server.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_REFERER => self::BASE . '/wiki/Item',
            CURLOPT_HTTPHEADER => [
                'Accept: ' . $accept,
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                'HTTP ' . $status . ($error !== '' ? ' · ' . $error : '')
            );
        }

        return $body;
    }

    /** @return array{0:DOMDocument,1:DOMXPath} */
    private function dom(string $html): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('HTML kon niet worden gelezen.');
        }

        return [$dom, new DOMXPath($dom)];
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        return self::BASE . '/' . ltrim($url, '/');
    }
}
