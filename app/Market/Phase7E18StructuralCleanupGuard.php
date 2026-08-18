<?php
declare(strict_types=1);

namespace LittyWatch\Market;

final class Phase7E18StructuralCleanupGuard
{
    public function __construct(private readonly \PDO $pdo) {}

    public function repair(array $row): array
    {
        $key = str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment = trim((string)($row['raw_segment']??''));

        if (
            preg_match('/^\s*(?:\.\s*)?\[\s*x?\d+\s+left\s*\]\s*$/iu', $segment)
            || preg_match('/^\s*\d+\s*(?:e|a|k)\.?\s*\[\s*x?\d+\s+left\s*\]\s*$/iu', $segment)
            || preg_match('/^\s*stk\s*$/iu', $segment)
            || preg_match('/^\s*\d+\s*stk\s*=\s*\d+(?:[.,]\d+)?\s*(?:e|a|k)\s*$/iu', $segment)
            || preg_match('/^\s*(?:\d+\s*)?e\s*for\s*100k\s*\(\s*x\d+\s*\)\s*$/iu', $segment)
            || preg_match('/^\s*for\s+100k\s*\(\s*x\d+\s*\)\s*$/iu', $segment)
        ) {
            return $this->reject($row, 'strict_catalog_generic', 0.15);
        }

        if (
            $key === 'alcohol'
            || $key === 'alcool'
            || $key === 'party-alchool'
            || preg_match('/^\s*alcoh?ol\s+stk\b/iu', $segment)
            || preg_match('/^\s*alcool\b/iu', $segment)
        ) {
            return $this->acceptByKey($row, 'Alcohol Points', 'market-points-alcohol');
        }

        if ($key === 'honeycombs-cumpcakes') {
            return $this->acceptResolved($row, 'Honeycomb');
        }

        if ($key === 'del-armor-rems' || preg_match('/^\s*del\s+armor\s+rems?\b/iu', $segment)) {
            return $this->acceptResolved($row, 'Deldrimor Armor Remnant');
        }

        if ($key === 'claws-of-bro' || preg_match('/^\s*claws\s+of\s+bro\b/iu', $segment)) {
            return $this->acceptResolved($row, 'Claws of the Broodmother');
        }

        if ($key === 'primeval' || preg_match('/^\s*primeval\s*$/iu', $segment)) {
            return $this->acceptResolved($row, 'Primeval Armor Remnant');
        }

        if (
            preg_match('/^\s*\d*\s*lockpics?\b/iu', $segment)
            || preg_match('/^\s*\d*\s*lockpicks?\b/iu', $segment)
            || str_contains($key, 'lockpics')
        ) {
            if (preg_match('/^\s*(\d+)\s*lockpic/i', $segment, $m)) {
                $row['quantity'] = (int)$m[1];
            }
            return $this->acceptResolved($row, 'Lockpick');
        }

        if ($key === 'the-deep-hm' || preg_match('/^\s*the\s+deep\s+hm\b/iu', $segment)) {
            return $this->reject($row, 'service_or_noise', 0.20);
        }

        if (
            $key === 'any-tormentend-weapons'
            || preg_match('/\bany\s+torment(?:ed|end)\s+weapons?\b/iu', $segment)
            || $key === 'mods-and-pets'
        ) {
            return $this->reject($row, 'collection_or_market_request', 0.20);
        }

        if (
            preg_match('/\b400\s+gold\s+value\b/iu', $segment)
            || preg_match('/^\s*q\d+\s*\+\s*\d+\s*energy\s+inscribable\s*$/iu', $segment)
            || $key === '11energy'
            || $key === 'while-enchanted'
            || preg_match('/\+\s*45\s*hp\s+while\s+enchanted\b/iu', $segment)
        ) {
            return $this->reject($row, 'modifier_fragment_unresolved', 0.20);
        }

        if ($key === 'miniature-celestial-dragon' && preg_match('/\bdragon\s+roots?\b/iu', $segment)) {
            return $this->acceptResolved($row, 'Dragon Root');
        }

        if (
            in_array($key, ['mallyx','miniature-mallyx'], true)
            && preg_match('/\bmallyx[\'’]s\s+\S+/iu', $segment)
            && !preg_match('/\bmini(?:ature|pet)?\b|\b(?:ded|unded)(?:icated)?\b/iu', $segment)
        ) {
            $row['quality_status'] = 'review';
            $row['quality_reason'] = 'catalog_first_unresolved';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.45);
            return $row;
        }

        if (in_array($key, ['sharp-pointy-stick','sunsp','drago'], true)) {
            $row['quality_status'] = 'review';
            $row['quality_reason'] = 'catalog_first_unresolved';
            return $row;
        }

        return $row;
    }

    private function reject(array $row, string $reason, float $cap): array
    {
        $row['quality_status'] = 'rejected';
        $row['quality_reason'] = $reason;
        $row['confidence'] = min((float)($row['confidence'] ?? 0), $cap);
        return $row;
    }

    private function acceptResolved(array $row, string $name): array
    {
        return $this->acceptByKey($row, $name, $this->resolveKey($name));
    }

    private function acceptByKey(array $row, string $name, string $key): array
    {
        $row['item'] = $name;
        $row['item_key'] = $key;
        $row['market_key'] = $key;
        $row['quality_status'] = 'accepted';
        $row['quality_reason'] = 'catalog_match';
        $row['confidence'] = max((float)($row['confidence'] ?? 0), 0.94);
        return $row;
    }

    private function resolveKey(string $name): string
    {
        $st = $this->pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
        $st->execute([$name]);
        $key = $st->fetchColumn();
        if ($key !== false) return (string)$key;

        $norm = mb_strtolower(trim(preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name));
        $st = $this->pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        $key = $st->fetchColumn();
        if ($key !== false) return (string)$key;

        return trim((string)preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
    }
}
