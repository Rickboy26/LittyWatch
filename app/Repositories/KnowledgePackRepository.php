<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use RuntimeException;

final class KnowledgePackRepository
{
    public function __construct(
        private readonly string $packDir,
        private readonly string $stagingDir,
    ) {
        if (!is_dir($this->packDir) && !mkdir($this->packDir, 0775, true) && !is_dir($this->packDir)) {
            throw new RuntimeException('Knowledge-packmap kon niet worden aangemaakt.');
        }
        if (!is_dir($this->stagingDir) && !mkdir($this->stagingDir, 0775, true) && !is_dir($this->stagingDir)) {
            throw new RuntimeException('Wiki-stagingmap kon niet worden aangemaakt.');
        }
    }

    /** @return array<string,mixed> */
    public function sources(): array
    {
        return $this->readObject($this->packDir . '/sources.json');
    }

    /** @return array<string,mixed> */
    public function metadata(): array
    {
        return $this->readObject($this->packDir . '/metadata.json');
    }

    /** @return list<array<string,mixed>> */
    public function items(): array
    {
        return $this->readList($this->packDir . '/items.json');
    }

    /** @return list<array<string,mixed>> */
    public function aliases(): array
    {
        return $this->readList($this->packDir . '/aliases.json');
    }

    /** @return list<array<string,mixed>> */
    public function stagedPages(): array
    {
        $all = [];
        foreach (glob($this->stagingDir . '/*.json') ?: [] as $path) {
            if (basename($path) === 'state.json') continue;
            foreach ($this->readList($path) as $page) {
                $title = trim((string)($page['title'] ?? ''));
                if ($title !== '') $all[strtolower($title)] = $page;
            }
        }
        return array_values($all);
    }

    /** @param list<array<string,mixed>> $pages */
    public function appendStage(string $profile, array $pages): int
    {
        $profile = preg_replace('/[^a-z0-9-]+/i', '-', $profile) ?: 'unknown';
        $path = $this->stagingDir . '/' . trim($profile, '-') . '.json';
        $existing = is_file($path) ? $this->readList($path) : [];
        $indexed = [];

        foreach (array_merge($existing, $pages) as $page) {
            if (!is_array($page)) continue;
            $title = trim((string)($page['title'] ?? ''));
            if ($title === '') continue;

            $redirects = [];
            foreach (($page['redirects'] ?? []) as $redirect) {
                if (is_array($redirect)) $redirect = $redirect['title'] ?? '';
                $redirect = trim((string)$redirect);
                if ($redirect !== '' && strcasecmp($redirect, $title) !== 0) {
                    $redirects[strtolower($redirect)] = $redirect;
                }
            }

            $indexed[strtolower($title)] = [
                'title' => $title,
                'pageid' => (int)($page['pageid'] ?? 0),
                'fullurl' => trim((string)($page['fullurl'] ?? '')),
                'extract' => trim((string)($page['extract'] ?? '')),
                'categories' => array_values(array_filter(array_map(
                    static fn(mixed $category): string => is_array($category)
                        ? trim((string)($category['title'] ?? ''))
                        : trim((string)$category),
                    $page['categories'] ?? []
                ))),
                'redirects' => array_values($redirects),
                'profile' => trim((string)($page['profile'] ?? $profile)),
                'kind' => trim((string)($page['kind'] ?? 'unknown')),
                'fetched_at' => date(DATE_ATOM),
            ];
        }

        $this->writeJson($path, array_values($indexed));
        return count($indexed);
    }

    /** @param list<array<string,mixed>> $items @param list<array<string,mixed>> $aliases */
    public function publish(array $items, array $aliases): void
    {
        $this->writeJson($this->packDir . '/items.json', $items);
        $this->writeJson($this->packDir . '/aliases.json', $aliases);
        $this->writeJson($this->packDir . '/metadata.json', [
            'version' => '5.1.0',
            'compiled_at' => date(DATE_ATOM),
            'item_count' => count($items),
            'alias_count' => count($aliases),
            'source' => 'wiki-staging+community',
        ]);
    }

    public function clearStage(): void
    {
        foreach (glob($this->stagingDir . '/*.json') ?: [] as $path) {
            @unlink($path);
        }
    }

    /** @return list<array<string,mixed>> */
    private function readList(string $path): array
    {
        if (!is_file($path)) return [];
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> */
    private function readObject(string $path): array
    {
        if (!is_file($path)) return [];
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param mixed $value */
    private function writeJson(string $path, mixed $value): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new RuntimeException('Knowledge-packmap bestaat niet: ' . $directory);
        }
        if (!is_writable($directory) && !is_file($path)) {
            throw new RuntimeException(
                'Knowledge-packmap is niet schrijfbaar voor PHP: ' . $directory .
                '. Geef de webserver schrijfrechten op app/Data/knowledge-pack.'
            );
        }
        if (is_file($path) && !is_writable($path)) {
            throw new RuntimeException(
                'Knowledge-packbestand is niet schrijfbaar voor PHP: ' . $path .
                '. Geef de webserver schrijfrechten op app/Data/knowledge-pack.'
            );
        }

        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        // Onderdruk PHP warnings in de HTTP-body. De API moet altijd geldige JSON
        // teruggeven; bij een schrijffout gooien we hieronder een nette exception.
        if (@file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            $lastError = error_get_last();
            $detail = trim((string)($lastError['message'] ?? 'onbekende schrijffout'));
            throw new RuntimeException(
                'Kon knowledge-packbestand niet schrijven: ' . $path .
                ($detail !== '' ? ' (' . $detail . ')' : '')
            );
        }
    }
}
