<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

final class Phase7E13ResidualGuard
{
    public function __construct(private readonly PDO $pdo) {}

    public function repair(array $row): array
    {
        $item = trim((string)($row['item'] ?? ''));
        $key = str_replace('_', '-', mb_strtolower(trim((string)($row['item_key'] ?? ''))));
        $segment = trim((string)($row['raw_segment'] ?? ''));

        if (
            $key === 'miniature-ghostly-priest'
            && (
                preg_match('/\bq\s*\d{1,2}\b/iu', $segment)
                || preg_match('/\bos\b/iu', $segment)
                || preg_match('/\bhct\b/iu', $segment)
                || preg_match('/\b(?:earth|fire|water|air|dom|domination|illu|illusion|blood|death|curses|sr|soul\s+reaping|heal|healing|prot|protection|smite|smiting|spawn|spawning|channel|channeling|resto|restoration)\b/iu', $segment)
            )
            && !preg_match('/\bmini(?:ature|pet)?\b|\b(?:ded|unded)(?:icated)?\b/iu', $segment)
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'strict_catalog_generic';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.30);
            return $row;
        }

        if (
            preg_match('/\bof\s+the\s+necro\b.*\+\s*5\s*sr\b.*\bscyt(?:he)?\b/iu', $segment)
            || $key === 'of-the-necro-5-sr-for-scyt'
        ) {
            return $this->accept($row, 'Scythe Grip of the Necromancer', 'scythe-grip-of-the-necromancer');
        }

        if (
            preg_match('/^\s*ice\s*dragon\s+blade\s*$/iu', $segment)
            || preg_match('/^\s*icedragon\s+blade\s*$/iu', $segment)
            || $key === 'icedragon-blade'
        ) {
            return $this->accept($row, 'Icy Dragon Sword', 'icy-dragon-sword');
        }

        if (
            preg_match('/^\s*d[-\s]?cakes?\b/iu', $segment)
            || str_starts_with($key, 'd-cakes')
        ) {
            return $this->accept($row, 'Birthday Cupcake', 'birthday-cupcake');
        }

        if (
            preg_match('/^\s*\d+\s+gold\s+value\s*$/iu', $segment)
            || $key === 'gold-value'
            || preg_match('/^\s*domination\s+and\s+illusion\s*$/iu', $segment)
            || $key === 'domination-and-illusion'
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'modifier_fragment_unresolved';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.30);
            return $row;
        }

        if (
            preg_match('/\bheart\s+of\s+shiverpeaks\b.*\bdd\b/iu', $segment)
            || preg_match('/^\s*\d+\s+titels?\b/iu', $segment)
            || $key === 'titels'
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'service_or_noise';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.25);
            return $row;
        }

        return $row;
    }

    private function accept(array $row, string $name, string $key): array
    {
        $row['item'] = $name;
        $row['item_key'] = $key;
        $row['market_key'] = $key;
        $row['quality_status'] = 'accepted';
        $row['quality_reason'] = 'catalog_match';
        $row['confidence'] = max((float)($row['confidence'] ?? 0), 0.92);
        return $row;
    }
}
