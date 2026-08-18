<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

final class Phase7E9LiveCleanupGuard
{
    public function __construct(private readonly PDO $pdo) {}

    public function repair(array $row): array
    {
        $item = trim((string)($row['item'] ?? ''));
        $segment = trim((string)($row['raw_segment'] ?? ''));
        $reason = (string)($row['quality_reason'] ?? '');

        if (str_starts_with(mb_strtolower($item), 'miniature ')) {
            $exact = $this->exactKbItem($item);
            if ($exact !== null) {
                $row['item'] = $exact['name'];
                $row['item_key'] = $exact['key'];
                $row['market_key'] = $exact['key'];

                if ($reason === 'miniature_variant_unresolved') {
                    $row['quality_status'] = 'review';
                    $row['quality_reason'] = 'miniature_variant_unresolved';
                }
            }
        }

        $itemLower = mb_strtolower(trim((string)($row['item'] ?? '')));

        if (
            in_array($itemLower, ['sweet', 'sweets', 'mod and scripts', 'mods and scripts'], true)
            || preg_match('/^(?:sweet|sweets)(?:\s+\d+(?:[.,]\d+)?\s*(?:e|a|k|g)(?:\/(?:st|stk|stack))?)?$/iu', $segment)
            || preg_match('/^mods?\s+and\s+scripts?$/iu', $segment)
            || preg_match('/^weapons?\s+q\d+\s+gold\s+inscribable\b/iu', $segment)
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'collection_or_market_request';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.40);
            return $row;
        }

        if (
            in_array($itemLower, ['staff','bow','axe','wand','shield','daggers','hammer','sword','spear','scythe','focus item'], true)
            && in_array($reason, ['low_confidence','strict_catalog_generic','catalog_first_unresolved'], true)
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'strict_catalog_generic';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.40);
        }

        return $row;
    }

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

        return [
            'key'=>(string)$rows[0]['key'],
            'name'=>(string)$rows[0]['name'],
        ];
    }
}
