<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Phase 2M market taxonomy.
 *
 * The catalog answers "what exact item is this?". This class answers
 * "what role does this token/category play?" so profession, quantity and
 * market-context fragments can never be promoted to inventory items.
 */
final class ItemTaxonomy
{
    public function __construct(private readonly array $data = []) {}

    public function categoryKind(string $category): string
    {
        return (string)($this->data['hierarchy'][$category]['kind'] ?? 'unknown');
    }

    public function parent(string $category): ?string
    {
        $parent = $this->data['hierarchy'][$category]['parent'] ?? null;
        return is_string($parent) && $parent !== '' ? $parent : null;
    }

    public function isGenericName(string $name): bool
    {
        $needle = mb_strtolower(trim($name));
        foreach (($this->data['generic_item_names'] ?? []) as $generic) {
            if ($needle === mb_strtolower((string)$generic)) return true;
        }
        return false;
    }

    public function isConcreteMatch(array $item): bool
    {
        if (($item['category'] ?? '') === 'generic-weapon-family') return false;
        if ($this->isGenericName((string)($item['item'] ?? $item['name'] ?? ''))) return false;
        $kind = $this->categoryKind((string)($item['category'] ?? ''));
        return $kind === 'concrete' || $kind === 'component' || $kind === 'unknown';
    }

    /** @return array{kind:string,reason:string}|null */
    public function classifyNonItemContext(string $candidate): ?array
    {
        $c = trim(mb_strtolower($candidate));
        $c = trim($c, " \t\n\r\0\x0B|,;:-_()[]{}\"");
        if ($c === '') return ['kind'=>'noise','reason'=>'taxonomy_empty'];

        foreach (($this->data['professions'] ?? []) as $token) {
            if ($c === mb_strtolower((string)$token)) return ['kind'=>'generic','reason'=>'profession_context'];
        }
        foreach (($this->data['quantity_context'] ?? []) as $token) {
            if ($c === mb_strtolower((string)$token)) return ['kind'=>'noise','reason'=>'quantity_context'];
        }
        foreach (($this->data['market_context'] ?? []) as $token) {
            if ($c === mb_strtolower((string)$token)) return ['kind'=>'noise','reason'=>'market_context'];
        }
        foreach (($this->data['dedication_context'] ?? []) as $token) {
            if ($c === mb_strtolower((string)$token)) return ['kind'=>'noise','reason'=>'dedication_context'];
        }

        // Quantity/trade leftovers produced after a valid item+price was split.
        if (preg_match('/^(?:\d+\s+)?stacks?\)?$/iu', $c)) return ['kind'=>'noise','reason'=>'quantity_context'];
        if (preg_match('/^stacks?\s*(?:=|:|,|\/|\\|-)*\s*(?:trade|pm)?$/iu', $c)) return ['kind'=>'noise','reason'=>'quantity_trade_context'];
        if (preg_match('/^(?:or\s+)?\d+(?:[.,]\d+)?\s*(?:a|e|k|g)$/iu', $c)) return ['kind'=>'noise','reason'=>'alternate_price_context'];
        if (preg_match('/^(?:each\s+)?(?:open\s+)?tra(?:de)?$/iu', $c)) return ['kind'=>'noise','reason'=>'trade_fragment'];

        return null;
    }
}
