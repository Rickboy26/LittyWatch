<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ParserEngine
{
    private Normalizer $normalizer;
    private OfferSplitter $splitter;
    private Tokenizer $tokenizer;
    private ItemMatcher $itemMatcher;
    private ModifierMatcher $modifierMatcher;
    private PriceMatcher $priceMatcher;
    private ConfidenceScorer $confidenceScorer;
    private ?CategoryExpander $categoryExpander = null;

    public function __construct(Catalog $catalog)
    {
        $this->normalizer = new Normalizer();
        $this->splitter = new OfferSplitter();
        $this->tokenizer = new Tokenizer();
        $this->itemMatcher = new ItemMatcher($catalog);
        $this->modifierMatcher = new ModifierMatcher($catalog);
        $this->priceMatcher = new PriceMatcher();
        $this->confidenceScorer = new ConfidenceScorer($catalog);
        if ($catalog->knowledgeBase() !== null) $this->categoryExpander = new CategoryExpander($catalog->knowledgeBase());
    }

    /** @return list<ParsedOffer> */
    public function parse(string $message): array
    {
        $normalized = $this->normalizer->normalize($message);
        $results = [];

        foreach ($this->splitter->split($normalized) as $block) {
            $segments = $this->splitSegments($block['text']);
            foreach ($segments as $segment) {
                $results = array_merge($results, $this->parseSegment($block['trade_type'], $segment));
            }
        }
        return $results;
    }

    /** @return list<ParsedOffer> */
    private function parseSegment(string $tradeType, string $segment): array
    {
        $segment = trim($segment, " \t\n\r\0\x0B|,;");
        if ($segment === '') return [];

        $items = $this->itemMatcher->matchAll($segment);
        if ($this->categoryExpander !== null) {
            $expanded = $this->categoryExpander->expand($segment);
            if (count($expanded) > count($items)) $items = $expanded;
        }
        if ($items === []) {
            $price = $this->priceMatcher->parse($segment);
            [$confidence, $status, $reason] = $this->confidenceScorer->score(null, [], $price, $segment);
            return [new ParsedOffer(
                $tradeType,
                $this->fallbackName($segment, $price),
                $this->key($this->fallbackName($segment, $price)),
                $this->modifierMatcher->match($segment),
                $price,
                $confidence,
                $status,
                $reason,
                $segment,
                $this->tokenizer->tokenize($segment),
            )];
        }

        // Multiple catalog items sharing one explicit package price stay a bundle.
        $wholePrice = $this->priceMatcher->parse($segment);
        if (count($items) > 1 && $wholePrice->amount !== null && preg_match('/\b(?:package|bundle|all unidentified|together)\b/i', $segment)) {
            $names = array_values(array_unique(array_column($items, 'item')));
            $itemName = 'Bundle: ' . implode(' + ', $names);
            return [new ParsedOffer(
                $tradeType,
                $itemName,
                $this->key($itemName),
                $this->modifierMatcher->match($segment),
                $wholePrice,
                0.92,
                'accepted',
                'explicit_bundle',
                $segment,
                $this->tokenizer->tokenize($segment),
            )];
        }

        $offers = [];
        foreach ($items as $index => $item) {
            $start = $item['start'];
            $end = $items[$index + 1]['start'] ?? mb_strlen($segment);
            $slice = trim(mb_substr($segment, $start, $end - $start), " \t\n\r\0\x0B|,;");
            if ($slice === '') $slice = $segment;
            $price = $this->priceMatcher->parse($slice);
            if ($price->amount === null && count($items) === 1) $price = $wholePrice;
            $modifiers = $this->modifierMatcher->match($slice);
            [$confidence, $status, $reason] = $this->confidenceScorer->score($item, $modifiers, $price, $slice);
            $offers[] = new ParsedOffer(
                $tradeType,
                $item['item'],
                $item['key'],
                $modifiers,
                $price,
                $confidence,
                $status,
                $reason,
                $slice,
                $this->tokenizer->tokenize($slice),
            );
        }
        return $offers;
    }

    /** @return list<string> */
    private function splitSegments(string $text): array
    {
        // Strong separators. Commas are intentionally retained for variant lists.
        $parts = preg_split('/\s*(?:\|{1,2}|\/{2,}|;+)\s*/u', $text) ?: [$text];
        return array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));
    }

    private function fallbackName(string $segment, ParsedPrice $price): string
    {
        $name = preg_replace('/\b(?:wtb|wts|wtt|buying|selling)\b/i', '', $segment) ?? $segment;
        if ($price->raw !== null) $name = str_replace($price->raw, '', $name);
        $name = preg_replace('/\b(?:pm|wsp|offer|offers|each|ea)\b.*$/i', '', $name) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B-:;,/|<>+=");
        return mb_substr($name !== '' ? $name : 'Unknown', 0, 120);
    }

    private function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($value)) ?? '');
    }
}
