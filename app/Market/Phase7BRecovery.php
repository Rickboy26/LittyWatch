<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * LITTYWATCH_PHASE7B_CONSERVATIVE_CATALOG_RECOVERY
 *
 * Recover only high-signal Kamadan shorthand/typos that can still be proven
 * against the active knowledge-base catalogue. No synthetic identities are
 * created here: every returned item must exist uniquely in kb_items/kb_aliases.
 */
final class Phase7BRecovery
{
    /** @var list<string> */
    private const GENERIC = [
        'axe','axes','shield','shields','staff','staves','staffs','scythe','scythes',
        'sword','swords','hammer','hammers','spear','spears','wand','wands','dagger',
        'daggers','focus','focus item','bow','bows','weapon','weapons','tonic','miniature',
    ];

    /**
     * Regex => catalogue names/aliases to try, in preference order.
     * A rule is useful only when one of these targets resolves uniquely in KB.
     *
     * @var array<string,list<string>>
     */
    private const SAFE_RULES = [
        '/\bobb(?:y|i|ie)?s?\s+sheads?\b/iu' => ['Obsidian Shard'],
        '/\bobsidian(?:s)?\s+(?:shard|frag)(?:s)?\b/iu' => ['Obsidian Shard'],
        '/\blockpics?\b/iu' => ['Lockpick'],
        '/\bmoos+\s*spider\s+eggs?\b/iu' => ['Moss Spider Egg'],
        '/\b(?:reg(?:ular)?\s+)?balt(?:h)?\s+flames?\b/iu' => ['Flame of Balthazar'],
        '/\bflames?\s+of\s+balth(?:azar)?\b/iu' => ['Flame of Balthazar'],
        '/\brezz(?:es)?(?:\s+scrolls?)?\b/iu' => ['Scroll of Resurrection'],
        '/\bscrolls?\s+of\s+resurrection\b/iu' => ['Scroll of Resurrection'],
        '/\bsilver\s+dyes?\b/iu' => ['Silver Dye'],
        '/\bblue\s+rocks?\b/iu' => ['Blue Rock Candy'],
        '/\bcreme(?:\s+brulee)?\b/iu' => ['Crème Brûlée', 'Creme Brulee'],
        '/\bgold(?:en)?\s+z\s*-?\s*coins?\b/iu' => ['Gold Zaishen Coin'],
        '/\bgold\s+zc\b/iu' => ['Gold Zaishen Coin'],
        '/\bfow\s+scrolls?\b/iu' => [
            'Scroll of Passage to the Fissure of Woe',
            'Passage Scroll to the Fissure of Woe',
        ],
    ];

    /** @var array<string,string> */
    private const TYPO_REWRITES = [
        'fellblede' => 'fellblade',
        'diamod aeg' => 'diamond aegis',
        'demoncrest' => 'demon crest',
        'moosspider' => 'moss spider',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $row @return array{key:string,name:string,reason:string}|null */
    public function resolve(array $row, string $message): ?array
    {
        $item = trim((string)($row['item'] ?? ''));
        $segment = trim((string)($row['raw_segment'] ?? ''));
        $context = trim($segment !== '' ? $segment : $message);
        if ($context === '' && $item === '') return null;

        $haystack = trim($item . ' ' . $context);

        // Never turn an unspecified miniature into a concrete miniature here.
        // Miniature dedication/identity remains owned by CatalogFirstResolver.
        if ($this->looksLikeMiniature($haystack)) return null;

        foreach (self::SAFE_RULES as $pattern => $targets) {
            if (preg_match($pattern, $haystack) !== 1) continue;
            foreach ($targets as $target) {
                $resolved = $this->uniqueExactOrAlias($target);
                if ($resolved !== null && !$this->isGeneric($resolved['name'])) {
                    return $resolved + ['reason'=>'phase7b_safe_alias'];
                }
            }
        }

        // Concrete weapon recovery is only attempted when the parser itself is
        // generic. This prevents a typo elsewhere in a valid concrete listing
        // from replacing the already-resolved item.
        if (!$this->isGeneric($item)) return null;

        $rewritten = KnowledgeBase::normalize($context);
        foreach (self::TYPO_REWRITES as $from => $to) {
            $rewritten = preg_replace('/(?:^|\s)'.preg_quote($from,'/').'(?:$|\s)/u', ' '.$to.' ', $rewritten) ?? $rewritten;
        }
        $rewritten = trim(preg_replace('/\s+/u',' ',$rewritten) ?? $rewritten);

        $hits = $this->embeddedConcreteHits($rewritten);
        if (count($hits) !== 1) return null;
        return $hits[0] + ['reason'=>'phase7b_embedded_concrete'];
    }

    /** @return list<array{key:string,name:string}> */
    private function embeddedConcreteHits(string $context): array
    {
        if ($context === '') return [];
        $rows = $this->pdo->query(
            "SELECT i.key,i.name,a.alias FROM kb_items i " .
            "LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1"
        )->fetchAll();

        /** @var array<string,array{key:string,name:string,len:int}> $hits */
        $hits = [];
        foreach ($rows as $row) {
            $key = (string)$row['key'];
            $name = CanonicalMarketIdentity::nameFor((string)$row['name'],$key);
            if ($this->isGeneric($name) || CanonicalMarketIdentity::isWikiDisambiguator($name)) continue;

            foreach ([(string)$row['name'], (string)($row['alias'] ?? '')] as $label) {
                $needle = KnowledgeBase::normalize($label);
                if ($needle === '' || mb_strlen($needle) < 6 || $this->isGeneric($needle)) continue;
                if (preg_match('/(?:^|\s)'.preg_quote($needle,'/').'(?:$|\s)/u',$context) !== 1) continue;
                $len = mb_strlen($needle);
                if (!isset($hits[$key]) || $len > $hits[$key]['len']) {
                    $hits[$key] = ['key'=>$key,'name'=>$name,'len'=>$len];
                }
            }
        }

        if ($hits === []) return [];
        usort($hits, static fn(array $a,array $b): int => $b['len'] <=> $a['len']);
        $max = $hits[0]['len'];
        $winners = array_values(array_filter($hits, static fn(array $h): bool => $h['len'] === $max));
        if (count($winners) !== 1) return [];
        return [['key'=>$winners[0]['key'],'name'=>$winners[0]['name']]];
    }

    /** @return array{key:string,name:string}|null */
    private function uniqueExactOrAlias(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') return null;

        $st = $this->pdo->prepare(
            "SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n)) LIMIT 2"
        );
        $st->execute([':n'=>$value]);
        $rows = $st->fetchAll();
        if (count($rows) === 1) {
            return [
                'key'=>(string)$rows[0]['key'],
                'name'=>CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key']),
            ];
        }

        $norm = KnowledgeBase::normalize($value);
        if ($norm === '') return null;
        $st = $this->pdo->prepare(
            "SELECT i.key,i.name FROM kb_aliases a JOIN kb_items i ON i.key=a.item_key " .
            "WHERE i.active=1 AND a.normalized_alias=:a GROUP BY i.key,i.name LIMIT 2"
        );
        $st->execute([':a'=>$norm]);
        $rows = $st->fetchAll();
        if (count($rows) !== 1) return null;
        return [
            'key'=>(string)$rows[0]['key'],
            'name'=>CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key']),
        ];
    }

    private function looksLikeMiniature(string $text): bool
    {
        return preg_match('/\b(?:mini|miniature|minis|unded|undedicated|ded|dedicated)\b/iu',$text) === 1;
    }

    private function isGeneric(string $value): bool
    {
        return in_array(KnowledgeBase::normalize($value), self::GENERIC, true);
    }
}
