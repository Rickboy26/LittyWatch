<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Repositories\KnowledgePackRepository;

final class KnowledgePackService
{
    public function __construct(private readonly KnowledgePackRepository $repository) {}

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $staged = $this->repository->stagedPages();
        $profiles = [];
        foreach ($staged as $page) {
            $key = (string)($page['profile'] ?? 'unknown');
            $profiles[$key] = ($profiles[$key] ?? 0) + 1;
        }

        return [
            'sources' => $this->repository->sources(),
            'metadata' => $this->repository->metadata(),
            'staged_count' => count($staged),
            'staged_profiles' => $profiles,
            'sample' => array_slice($staged, 0, 30),
        ];
    }

    /** @param list<array<string,mixed>> $pages */
    public function stage(string $profile, string $kind, array $pages): array
    {
        $clean = [];
        foreach ($pages as $page) {
            if (!is_array($page)) continue;
            $page['profile'] = $profile;
            $page['kind'] = $kind;
            $clean[] = $page;
        }

        return [
            'saved' => $this->repository->appendStage($profile, $clean),
            'received' => count($clean),
        ];
    }

    /** @return array<string,int> */
    public function compile(): array
    {
        $pages = $this->repository->stagedPages();
        $items = [];
        $aliases = [];

        foreach ($this->repository->aliases() as $alias) {
            $aliasText = trim((string)($alias['alias'] ?? ''));
            $itemName = trim((string)($alias['item'] ?? ''));
            if ($aliasText === '' || $itemName === '') continue;
            $aliases[strtolower($aliasText)] = [
                'alias'=>$aliasText,
                'item'=>$itemName,
                'source'=>(string)($alias['source'] ?? 'community'),
            ];
        }

        foreach ($pages as $page) {
            $title = trim((string)($page['title'] ?? ''));
            if (!$this->isMarketItem($title, $page)) continue;

            $key = $this->key($title);
            $kind = $this->category((string)($page['kind'] ?? 'unknown'), $page);
            $pageAliases = [$title];

            foreach (($page['redirects'] ?? []) as $redirect) {
                $redirect = trim((string)$redirect);
                if ($redirect !== '') $pageAliases[] = $redirect;
            }

            $pageAliases = array_values(array_unique($pageAliases));
            $items[$key] = [
                'key'=>$key,
                'name'=>$title,
                'category'=>$kind,
                'aliases'=>$pageAliases,
                'wiki_url'=>(string)($page['fullurl'] ?? ''),
                'wiki_extract'=>(string)($page['extract'] ?? ''),
                'source'=>'guild-wars-wiki',
            ];

            foreach ($pageAliases as $alias) {
                $aliases[strtolower($alias)] = [
                    'alias'=>$alias,
                    'item'=>$title,
                    'source'=>'wiki-redirect',
                ];
            }
        }

        uasort($items, static fn(array $a,array $b): int => strcasecmp($a['name'],$b['name']));
        uasort($aliases, static fn(array $a,array $b): int => strcasecmp($a['alias'],$b['alias']));
        $this->repository->publish(array_values($items), array_values($aliases));

        return [
            'pages'=>count($pages),
            'items'=>count($items),
            'aliases'=>count($aliases),
        ];
    }

    public function clearStage(): void
    {
        $this->repository->clearStage();
    }

    /** @param array<string,mixed> $page */
    private function isMarketItem(string $title, array $page): bool
    {
        if ($title === '' || str_contains($title, ':')) return false;
        if (preg_match('/^(?:List of|Category|Template|User|Guild Wars Wiki|Feedback|Talk:)/iu', $title)) return false;

        $extract = strtolower((string)($page['extract'] ?? ''));
        $categories = strtolower(implode(' ', $page['categories'] ?? []));
        $combined = $extract . ' ' . $categories . ' ' . strtolower((string)($page['kind'] ?? ''));

        $positive = [
            'weapon','item','miniature','tonic','consumable','tome',
            'upgrade','inscription','bow','sword','axe','hammer','staff',
            'wand','focus','shield','dagger','scythe','spear'
        ];
        foreach ($positive as $term) {
            if (str_contains($combined, $term)) return true;
        }

        return false;
    }

    /** @param array<string,mixed> $page */
    private function category(string $kind, array $page): string
    {
        if ($kind !== '' && $kind !== 'unknown') return $kind;
        $text = strtolower(
            (string)($page['extract'] ?? '') . ' ' . implode(' ', $page['categories'] ?? [])
        );

        return match (true) {
            str_contains($text,'miniature') => 'miniature',
            str_contains($text,'tonic') => 'tonic',
            str_contains($text,'inscription') => 'inscription',
            str_contains($text,'upgrade') => 'weapon-upgrade',
            str_contains($text,'tome') => 'tome',
            str_contains($text,'consumable') => 'consumable',
            str_contains($text,'unique item') || str_contains($text,'green item') => 'unique-item',
            default => 'item',
        };
    }

    private function key(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u','-', $value) ?? $value;
        return trim($value,'-');
    }
}
