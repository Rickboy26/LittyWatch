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
    private TradeNotationCleaner $tradeNotationCleaner;
    private ExchangeMatcher $exchangeMatcher;
    private MessageClassifier $classifier;
    private SmartSegmenter $segmenter;
    private SemanticNormalizer $semantic;
    private SetQuantityResolver $setResolver;
    private ?CategoryExpander $categoryExpander = null;
    private ?\LittyWatch\Knowledge\ProfileResolver $profileResolver = null;
    private ?AttributeMatcher $attributeMatcher = null;

    public function __construct(Catalog $catalog)
    {
        $this->normalizer = new Normalizer();
        $this->splitter = new OfferSplitter();
        $this->tokenizer = new Tokenizer();
        $this->itemMatcher = new ItemMatcher($catalog);
        $this->modifierMatcher = new ModifierMatcher($catalog);
        $this->priceMatcher = new PriceMatcher();
        $this->confidenceScorer = new ConfidenceScorer($catalog);
        $this->tradeNotationCleaner = new TradeNotationCleaner();
        $this->exchangeMatcher = new ExchangeMatcher();
        $dynamic = null;
        if ($catalog->knowledgeBase() !== null) {
            $knowledgeRepo = new \LittyWatch\Repositories\ParserKnowledgeRepository($catalog->knowledgeBase());
            $knowledgeRepo->install();
            $dynamic = new DynamicKnowledge($knowledgeRepo);
        }
        $this->classifier = new MessageClassifier($dynamic);
        $this->segmenter = new SmartSegmenter();
        $this->semantic = new SemanticNormalizer($dynamic);
        $this->setResolver = new SetQuantityResolver($dynamic);
        if ($catalog->knowledgeBase() !== null) {
            $this->categoryExpander = new CategoryExpander($catalog->knowledgeBase());
            $this->profileResolver = new \LittyWatch\Knowledge\ProfileResolver($catalog->knowledgeBase());
            $this->attributeMatcher = new AttributeMatcher($catalog->knowledgeBase());
        }
    }

    /** @return list<ParsedOffer> */
    public function parse(string $message): array
    {
        $normalized = $this->normalizer->normalize($message);
        $results = [];

        foreach ($this->splitter->split($normalized) as $block) {
            if ($this->classifier->classify($block['text'])['kind'] !== 'market') continue;
            foreach ($this->segmenter->split($block['text']) as $segment) {
                if ($this->classifier->classify($segment)['kind'] !== 'market') continue;
                $results = array_merge($results, $this->parseSegment($block['trade_type'], $this->semantic->normalize($segment)));
            }
        }
        return $results;
    }

    /** @return list<ParsedOffer> */
    private function parseSegment(string $tradeType, string $segment): array
    {
        $segment = trim($segment, " \t\n\r\0\x0B|,;");
        if ($segment === '') return [];

        if ($tradeType === 'trade') {
            $exchangeOffers = $this->parseExchangeSegment($segment);
            if ($exchangeOffers !== []) {
                return $exchangeOffers;
            }
        }

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
            $setQuantity = $this->setResolver->resolve((string)$item['item'], $slice);
            if ($setQuantity !== null && $price->amount !== null) {
                $price = new ParsedPrice($price->amount,$price->currency,$price->ecto,'set',$setQuantity,$price->ecto!==null?$price->ecto/$setQuantity:null,$price->raw);
            }
            $modifiers = $this->modifierMatcher->match($slice);
            $attribute = $this->attributeMatcher?->match($slice);
            if ($attribute !== null) $modifiers['attribute'] = $attribute['name'];
            [$confidence, $status, $reason] = $this->confidenceScorer->score($item, $modifiers, $price, $slice);
            $profileData = $this->profileResolver?->resolve($item['key'], $item['category'] ?? 'unknown', $modifiers) ?? [
                'profile' => [], 'relevant' => $modifiers, 'market_key' => $item['key']
            ];
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
                $profileData['profile'],
                $profileData['relevant'],
                $profileData['market_key'],
            );
        }
        return $offers;
    }


    /** @return list<ParsedOffer> */
    private function parseExchangeSegment(string $segment): array
    {
        $exchange = $this->exchangeMatcher->parse($segment);
        if ($exchange === null) {
            return [];
        }

        $sources = $this->matchSide($exchange['left']);
        $targets = $this->matchSide($exchange['right']);

        $target = $targets[0] ?? [
            'item' => $this->exchangeMatcher->normalizeFallbackName($exchange['right']),
            'key' => $this->key($this->exchangeMatcher->normalizeFallbackName($exchange['right'])),
        ];

        if ($sources === []) {
            foreach ($this->exchangeMatcher->splitFallbackSources($exchange['left']) as $sourceName) {
                $sources[] = [
                    'item' => $sourceName,
                    'key' => $this->key($sourceName),
                    'category' => 'exchange-fallback',
                ];
            }
        }

        $offers = [];
        foreach ($sources as $source) {
            $offers[] = new ParsedOffer(
                'trade',
                (string)$source['item'],
                (string)$source['key'],
                [],
                new ParsedPrice(
                    null,
                    null,
                    null,
                    'barter',
                    (float)$exchange['give_quantity'],
                    null,
                    (string)$exchange['raw_ratio'],
                ),
                0.9,
                'accepted',
                'explicit_item_exchange',
                $segment,
                $this->tokenizer->tokenize($segment),
                [],
                [],
                (string)$source['key'],
                [
                    'target_item' => (string)$target['item'],
                    'target_item_key' => (string)$target['key'],
                    'give_quantity' => (float)$exchange['give_quantity'],
                    'receive_quantity' => (float)$exchange['receive_quantity'],
                    'ratio' => (string)$exchange['raw_ratio'],
                ],
            );
        }

        return $offers;
    }

    /** @return list<array<string,mixed>> */
    private function matchSide(string $text): array
    {
        $items = $this->itemMatcher->matchAll($text);

        if ($this->categoryExpander !== null) {
            $expanded = $this->categoryExpander->expand($text);
            if (count($expanded) > count($items)) {
                $items = $expanded;
            }
        }

        return $items;
    }


    /** @return list<string> */
    private function splitSegments(string $text): array
    {
        return $this->segmenter->split($text);
    }

    private function fallbackName(string $segment, ParsedPrice $price): string
    {
        $name = preg_replace('/\b(?:wtb|wts|wtt|buying|selling)\b/i', '', $segment) ?? $segment;
        if ($price->raw !== null) $name = str_replace($price->raw, '', $name);
        $name = preg_replace('/\b(?:pm|wsp|offer|offers)\b.*$/i', '', $name) ?? $name;
        $name = $this->tradeNotationCleaner->cleanItemCandidate($name);
        return mb_substr($name !== '' ? $name : 'Unknown', 0, 120);
    }

    private function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($value)) ?? '');
    }
}
