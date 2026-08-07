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
    private MarketMessageGate $messageGate;
    private GrammarSegmenter $grammarSegmenter;
    private MarketMetadataExtractor $metadataExtractor;
    private GenericItemRecognizer $genericRecognizer;
    private ContextualSegmentExpander $contextualSegmentExpander;
    private ReviewCandidateClassifier $reviewCandidateClassifier;
    private ItemTaxonomy $taxonomy;
    private SharedOfferListExpander $sharedOfferListExpander;
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
        if ($catalog->database() !== null) {
            $knowledgeRepo = new \LittyWatch\Repositories\ParserKnowledgeRepository($catalog->database());
            $knowledgeRepo->install();
            $dynamic = new DynamicKnowledge($knowledgeRepo);
        }
        $this->classifier = new MessageClassifier($dynamic);
        $this->segmenter = new SmartSegmenter();
        $this->semantic = new SemanticNormalizer($dynamic);
        $this->setResolver = new SetQuantityResolver($dynamic);
        $this->messageGate = new MarketMessageGate();
        $this->grammarSegmenter = new GrammarSegmenter();
        $this->metadataExtractor = new MarketMetadataExtractor();
        $this->genericRecognizer = new GenericItemRecognizer();
        $this->contextualSegmentExpander = new ContextualSegmentExpander($this->itemMatcher);
        $this->taxonomy = new ItemTaxonomy($catalog->taxonomy());
        $this->reviewCandidateClassifier = new ReviewCandidateClassifier($this->taxonomy);
        $this->sharedOfferListExpander = new SharedOfferListExpander();
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
        $gate = $this->messageGate->inspect($normalized);
        if (!$gate['accepted']) return [];

        $results = [];
        foreach ($this->splitter->split($normalized) as $block) {
            if ($this->classifier->classify($block['text'])['kind'] !== 'market') continue;

            // Normalize the complete block first. Some market shorthand (for example
            // birthday ranges) expands into multiple logical offers before segmentation.
            $blockText = $this->semantic->normalize($block['text']);
            // Tome advertisements use profession shorthand and comma/space lists that
            // need semantic expansion before the generic grammar splitter flattens them.
            $sharedListSegments = $this->sharedOfferListExpander->expand($blockText);
            $smartSegments = preg_match('/\btomes?\b/iu', $blockText) ? $this->segmenter->split($blockText) : [];
            $segments = $sharedListSegments !== null
                ? $sharedListSegments
                : (count($smartSegments) > 1 ? $smartSegments : $this->grammarSegmenter->split($blockText));
            if ($segments === []) $segments = $this->segmenter->split($blockText);
            $segments = $this->contextualSegmentExpander->expand($segments);

            foreach ($segments as $segment) {
                if ($this->classifier->classify($segment)['kind'] !== 'market') continue;
                $results = array_merge(
                    $results,
                    $this->parseSegment(
                        $block['trade_type'],
                        $this->semantic->normalize($segment)
                    )
                );
            }
        }

        return $this->deduplicate($this->suppressLowConfidenceGenericShadows($results));
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

        // Suppress category/service-level market text before catalog matching.
        // Otherwise a generic phrase like "OS WEAPONS & SHIELDS" can partially
        // match a catalog token such as "Shield" and create a false offer.
        $segmentClass = $this->reviewCandidateClassifier->classify(
            $this->tradeNotationCleaner->cleanItemCandidate($segment),
            $segment
        );
        if (in_array($segmentClass['kind'], ['generic','service'], true)) return [];

        // Generic family searches such as "q5-7 flatbows" describe several
        // requirement variants. Expand the small range before normal matching.
        if (preg_match('/\b(?:q|r)\s*(\d{1,2})\s*-\s*(\d{1,2})\b/iu', $segment, $range)) {
            $genericRange = $this->genericRecognizer->recognize($segment);
            $from=(int)$range[1]; $to=(int)$range[2];
            if ($genericRange !== null && $to >= $from && ($to-$from) <= 10) {
                $expanded=[];
                for($q=$from;$q<=$to;$q++) {
                    $variant = preg_replace('/'.preg_quote($range[0],'/').'/u', 'q'.$q, $segment, 1) ?? $segment;
                    $expanded = array_merge($expanded, $this->parseSegment($tradeType, $variant));
                }
                return $expanded;
            }
        }

        $items = $this->itemMatcher->matchAll($segment);
        if ($this->categoryExpander !== null && $items === []) {
            // Phase 2K: group/category knowledge is fallback knowledge only.
            // A concrete catalog match (e.g. Raging Menzies in "FoW Green")
            // must never be replaced by a broad "green/unique item" expansion.
            $expanded = $this->categoryExpander->expand($segment);
            if ($expanded !== []) $items = $expanded;
        }
        if ($items === []) {
            $generic = $this->genericRecognizer->recognize($segment);
            if ($generic !== null) {
                $items = [$generic];
            } else {
                $price = $this->priceMatcher->parse($segment);
                $fallback = $this->fallbackName($segment, $price);
                if ($this->isNoiseCandidate($fallback, $segment)) return [];
                $candidateClass = $this->reviewCandidateClassifier->classify($fallback, $segment);
                if ($candidateClass['kind'] !== 'item') return [];
                $metadata = array_merge(
                    $this->modifierMatcher->match($segment),
                    $this->metadataExtractor->extract($segment)
                );
                [$confidence, $status, $reason] = $this->confidenceScorer->score(null, $metadata, $price, $segment);
                return [new ParsedOffer(
                    $tradeType,
                    $fallback,
                    $this->key($fallback),
                    $metadata,
                    $price,
                    $confidence,
                    $status,
                    $reason,
                    $segment,
                    $this->tokenizer->tokenize($segment),
                )];
            }
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
                $price = new ParsedPrice($price->amount,$price->currency,$price->ectoValue,'set',$setQuantity,$price->ectoValue!==null?$price->ectoValue/$setQuantity:null,$price->raw);
            }
            $modifiers = array_merge(
                $this->modifierMatcher->match($segment),
                $this->metadataExtractor->extract($segment),
                $this->modifierMatcher->match($slice),
                $this->metadataExtractor->extract($slice)
            );

            if (preg_match('/\b(?:q|rq|req(?:uirement)?)\s*([0-9]{1,2})\b/iu', $slice, $requirementMatch)) {
                $modifiers['requirement'] = 'q' . $requirementMatch[1];
            }

            $attribute = $this->attributeMatcher?->match($slice);
            if ($attribute !== null) {
                $modifiers['attribute'] = $attribute['name'];
                if (
                    !isset($modifiers['requirement'])
                    && preg_match('/\breq(?:uirement)?\s+' . preg_quote((string)$attribute['name'], '/') . '\b/iu', $slice)
                ) {
                    $modifiers['requirement'] = 'any';
                }
            }
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

    /**
     * Phase 2N: a low-confidence generic family produced by learned knowledge is
     * never better than the dedicated generic recognizer and frequently shadows a
     * concrete item (Miniature Polar Bear -> Miniature, Plagueborn Staff -> Staff).
     * Drop only review-level generic shadows; accepted generic market searches are
     * preserved.
     *
     * @param list<ParsedOffer> $offers
     * @return list<ParsedOffer>
     */
    private function suppressLowConfidenceGenericShadows(array $offers): array
    {
        return array_values(array_filter($offers, function (ParsedOffer $offer) use ($offers): bool {
            if ($offer->status !== 'review' || $offer->reason !== 'low_confidence') return true;
            if (!$this->taxonomy->isGenericName($offer->item)) return true;

            $generic = mb_strtolower(trim($offer->item));
            $segment = mb_strtolower(trim($offer->segment));

            // Generic collector buckets never form a useful price observation by
            // themselves. In the live queue they are shadow rows next to a concrete
            // mini/green, or broad category prose with no single item price.
            if (in_array($generic, ['miniature','unique item'], true)) return false;

            // Upgrade/component requests must not become the base weapon family.
            if (preg_match('/\b(?:wrap|wrapping|head|haft|grip|string|bowstring|pommel|core|mod|mods|vamp|zealous|swift|hale|patron|insightful|sundering|furious)\b/iu', $segment)) {
                return false;
            }

            // If an accepted concrete offer from the same parse clearly contains
            // this family name, the generic row is only a specificity shadow.
            foreach ($offers as $other) {
                if ($other === $offer || $other->status !== 'accepted') continue;
                if ($this->taxonomy->isGenericName($other->item)) continue;
                $otherItem = mb_strtolower($other->item);
                $otherSegment = mb_strtolower($other->segment);
                if (!str_contains($otherItem, $generic)) continue;
                $g = preg_replace('/[^a-z0-9]+/u', ' ', $segment) ?? $segment;
                $o = preg_replace('/[^a-z0-9]+/u', ' ', $otherSegment) ?? $otherSegment;
                $g = trim($g); $o = trim($o);
                if ($g === '' || $o === '' || str_contains($o, $g) || str_contains($g, $o) || str_contains($o, $generic)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function deduplicate(array $offers): array
    {
        $seen = [];
        $result = [];

        foreach ($offers as $offer) {
            $data = $offer->toArray();
            $signature = implode('|', [
                (string)$data['trade_type'],
                (string)$data['item_key'],
                json_encode($data['modifiers'] ?? [], JSON_UNESCAPED_UNICODE),
                (string)($data['exchange']['target_item_key'] ?? ''),
            ]);

            if (isset($seen[$signature])) continue;
            $seen[$signature] = true;
            $result[] = $offer;
        }

        return $result;
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


    private function isNoiseCandidate(string $candidate, string $segment): bool
    {
        $candidate = trim($candidate);
        if ($candidate === '' || $candidate === 'Unknown') return true;
        if (!preg_match('/[\p{L}\p{N}]/u', $candidate)) return true;

        $noise = preg_replace('/\b(?:q|r|rq|req(?:uirement)?)\s*\d{0,2}\b/iu', ' ', $candidate) ?? $candidate;
        $noise = preg_replace('/\b(?:es|fc|sr|df|spaw(?:ning)?|dom(?:ination)?|illu(?:sion)?|inspi(?:ration)?|heal(?:ing)?|smite|smiting|fire|water|air|earth|blood|death|curs(?:es)?|resto(?:ration)?|com(?:muning)?|chan(?:neling)?|str(?:ength)?|tac(?:tics)?|lead(?:ership)?)\b/iu', ' ', $noise) ?? $noise;
        $noise = preg_replace('/\b(?:insc(?:r(?:ibable|iptable)?)?|inscribable|os|old\s*school|unid(?:entified)?|unded(?:icated)?|ded(?:icated)?|pm|offer(?:s)?|each|ea|for\s+all)\b/iu', ' ', $noise) ?? $noise;
        $noise = preg_replace('/[\d\s.,:;!?.=_+\-\/|()\[\]]+/u', '', $noise) ?? $noise;
        if ($noise === '') return true;

        return (bool)preg_match('/^(?:for\s+all|each|ea|pm|offers?|price|tell\s+me\s+your\s+price)$/iu', $candidate);
    }

    private function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($value)) ?? '');
    }
}
