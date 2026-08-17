<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

/**
 * Phase 7E.8 FIX1
 *
 * Prevent named non-miniature items such as "Madruk's Prophecy" and
 * "... Fortune" clauses from being promoted to Miniature * merely because
 * a miniature alias contains the same proper name.
 *
 * If the exact named item exists in KB, use that canonical KB identity.
 * Otherwise keep it out of the accepted market by returning a review row.
 */
final class Phase7E8NamedCollisionGuard
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $row */
    public function repair(array $row): array
    {
        $segment = trim((string)($row['raw_segment'] ?? ''));
        if ($segment === '') return $row;

        $item = trim((string)($row['item'] ?? ''));
        $key  = trim((string)($row['item_key'] ?? ''));

        $isMini = str_starts_with(mb_strtolower($item), 'miniature ')
            || str_starts_with(str_replace('_','-',mb_strtolower($key)), 'miniature-')
            || in_array(mb_strtolower($item), ['miniature','mini'], true)
            || in_array(str_replace('_','-',mb_strtolower($key)), ['miniature','mini'], true);

        if (!$isMini) return $row;

        $named = $this->namedCollision($segment);
        if ($named === null) return $row;

        $exact = $this->exactKbItem($named);
        if ($exact !== null) {
            $row['item'] = $exact['name'];
            $row['item_key'] = $exact['key'];
            $row['market_key'] = $exact['key'];
            $row['quality_status'] = 'accepted';
            $row['quality_reason'] = 'catalog_match';
            $row['confidence'] = max(0.95, (float)($row['confidence'] ?? 0));
            return $row;
        }

        // Important: never keep the false miniature identity accepted/reviewed.
        // The phrase is not in KB, so surface it only as unresolved parser data.
        $row['item'] = $named;
        $row['item_key'] = $this->slug($named);
        $row['market_key'] = $row['item_key'];
        $row['quality_status'] = 'review';
        $row['quality_reason'] = 'catalog_first_unresolved';
        $row['confidence'] = min(0.60, (float)($row['confidence'] ?? 0.60));
        return $row;
    }

    private function namedCollision(string $segment): ?string
    {
        $segment = str_replace(['’','´','`'], "'", $segment);

        if (preg_match("/\bMadruk(?:'s)?\s+Prophecy\b/iu", $segment)) {
            return "Madruk's Prophecy";
        }

        // Generic Fortune guard. Preserve the proper-name phrase, but never
        // invent a KB item if it does not exist.
        if (preg_match("/\b([A-Z][A-Za-z'-]{2,})(?:'s)?\s+Fortune\b/u", $segment, $m)) {
            return $m[1] . "'s Fortune";
        }

        return null;
    }

    /** @return array{key:string,name:string}|null */
    private function exactKbItem(string $name): ?array
    {
        $normalized = \LittyWatch\Knowledge\KnowledgeBase::normalize($name);
        $st = $this->pdo->prepare(
            "SELECT i.key,i.name
             FROM kb_items i
             LEFT JOIN kb_aliases a ON a.item_key=i.key
             WHERE i.active=1
               AND (
                    lower(trim(i.name))=lower(trim(:name))
                    OR a.normalized_alias=:alias
               )
             GROUP BY i.key,i.name
             LIMIT 2"
        );
        $st->execute([':name'=>$name, ':alias'=>$normalized]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) return null;
        return ['key'=>(string)$rows[0]['key'], 'name'=>(string)$rows[0]['name']];
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(str_replace(['’','´','`'], "'", trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;
        return trim($value, '-');
    }
}
