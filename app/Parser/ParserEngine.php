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
    private MarketBundleExpander $marketBundleExpander;
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
        $this->marketBundleExpander = new MarketBundleExpander();
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
            // Phase 3V: remove explicit negative/exclusion clauses before item
            // segmentation. "unid golds (no scythes, shields or spears)" is one
            // offer for Unidentified Gold, not four separate offers.
            $blockText = $this->stripNegativeItemClauses($blockText);
            // Tome advertisements use profession shorthand and comma/space lists that
            // need semantic expansion before the generic grammar splitter flattens them.
            // LITTYWATCH_PHASE4C_BUNDLE_RESOLVER
            $phase4cBundleSegments = $this->marketBundleExpander->expand($blockText);
            $sharedListSegments = $phase4cBundleSegments ?? $this->sharedOfferListExpander->expand($blockText);
            $smartSegments = preg_match('/\btomes?\b|^\s*(?:elite|normal|regular)\s+(?:war(?:rior)?|ranger|monk|necro(?:mancer)?|mes(?:mer)?|ele(?:mentalist)?|assassin|rit(?:ualist)?|para(?:gon)?|derv(?:ish)?)\b/iu', $blockText) ? $this->segmenter->split($blockText) : [];
            $segments = $sharedListSegments !== null
                ? $sharedListSegments
                : (count($smartSegments) > 1 ? $smartSegments : $this->grammarSegmenter->split($blockText));
            if ($segments === []) $segments = $this->segmenter->split($blockText);
            $segments = $this->splitExplicitRequirementCommaBoundaries($segments);
            $segments = $this->splitExplicitWeaponFamilyBoundaries($segments);
            $segments = $this->contextualSegmentExpander->expand($segments);
            // Phase 3V: expand comma-separated requirement/attribute variants and
            // inherit the concrete item identity across shorthand continuation
            // clauses (e.g. "BDS q9 FC 35a | q11 Inspa 12a").
            $segments = $this->expandVariantClauses($segments);
            $segments = $this->inheritConcreteItemContext($segments);

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

        $results = $this->promoteExplicitGenericRequirements($results);
        $results = $this->promoteExplicitGenericMarketSearches($results, $normalized);
        $results = $this->promotePhase2RTrustedCatalogMatches($results, $normalized);
        // Phase 6H.1: dedication qualifiers can be stripped by semantic / grammar
        // cleanup after the miniature itself has already been canonicalized. Restore
        // ded/unded from the original normalized market text before deduplication so
        // the two states also receive distinct market keys.
        $results = $this->restoreMiniatureDedication($results, $normalized);
        return $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));
    }

    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function restoreMiniatureDedication(array $offers, string $source): array
    {
        foreach ($offers as $index => $offer) {
            if (!str_starts_with(mb_strtolower($offer->item), 'miniature ')) continue;
            if (isset($offer->modifiers['dedication'])) continue;

            $name = preg_replace('/^Miniature\s+/iu', '', $offer->item) ?? $offer->item;
            $aliases = [preg_quote($name, '/')];
            if (mb_strtolower($name) === 'ghostly priest') $aliases[] = 'g\s*priest';
            $itemPattern = '(?:miniature\s+)?(?:' . implode('|', $aliases) . ')';
            $matches = [];

            if (preg_match_all('/\b(unded(?:icated)?|ded(?:icated)?)\s+' . $itemPattern . '\b/iu', $source, $m)) {
                foreach ($m[1] as $token) $matches[] = str_starts_with(mb_strtolower((string)$token), 'unded') ? 'undedicated' : 'dedicated';
            }
            if (preg_match_all('/\b' . $itemPattern . '\s+(unded(?:icated)?|ded(?:icated)?)\b/iu', $source, $m)) {
                foreach ($m[1] as $token) $matches[] = str_starts_with(mb_strtolower((string)$token), 'unded') ? 'undedicated' : 'dedicated';
            }

            $matches = array_values(array_unique($matches));
            // Ambiguous messages that mention the same miniature in both states are
            // intentionally left untouched rather than assigning the wrong variant.
            if (count($matches) !== 1) continue;

            $dedication = $matches[0];
            $modifiers = $offer->modifiers;
            $modifiers['dedication'] = $dedication;
            $relevant = $offer->relevantProperties;
            $relevant['dedication'] = $dedication;
            $marketKey = preg_replace('/\|dedication:[^|]+/iu', '', $offer->marketKey) ?? $offer->marketKey;
            if ($marketKey === '') $marketKey = $offer->itemKey;
            $marketKey .= '|dedication:' . $dedication;

            $offers[$index] = new ParsedOffer(
                $offer->tradeType,
                $offer->item,
                $offer->itemKey,
                $modifiers,
                $offer->price,
                $offer->confidence,
                $offer->status,
                $offer->reason,
                $offer->segment,
                $offer->tokens,
                $offer->profile,
                $relevant,
                $marketKey,
                $offer->exchange,
            );
        }
        return $offers;
    }

    /**
     * A comma followed by an explicit q/r requirement commonly starts the next
     * item: "Q9 FC BDS, Q8 Tac Shield". Keeping that as one segment causes
     * Q8/Tactics to leak backwards into the BDS. Parenthesized exclusions are
     * protected so "q9 any(no chann,curses)" stays intact.
     * @param list<string> $segments
     * @return list<string>
     */
    private function splitExplicitRequirementCommaBoundaries(array $segments): array
    {
        $out=[];
        foreach($segments as $segment){
            $parts=[];$buf='';$depth=0;$len=mb_strlen($segment);
            for($i=0;$i<$len;$i++){
                $ch=mb_substr($segment,$i,1);
                if($ch==='(' || $ch==='['){$depth++;$buf.=$ch;continue;}
                if($ch===')' || $ch===']'){$depth=max(0,$depth-1);$buf.=$ch;continue;}
                if($ch===',' && $depth===0){
                    $tail=ltrim(mb_substr($segment,$i+1));
                    if(preg_match('/^(?:q|r|rq|req(?:uirement)?)\s*\d{1,2}\b/iu',$tail)){
                        if(trim($buf)!=='')$parts[]=trim($buf);
                        $buf='';
                        continue;
                    }
                }
                $buf.=$ch;
            }
            if(trim($buf)!=='')$parts[]=trim($buf);
            foreach(($parts?:[$segment]) as $part)$out[]=$part;
        }
        return $out;
    }

    /**
     * Phase 6G: explicit weapon-family words start a new ownership clause even
     * when Kamadan shorthand omits commas. This protects the previous concrete
     * skin from metadata that belongs to a later generic weapon, e.g.
     * "BDS/ q8 gold inscribable scarabshell shield" and
     * "Ghostly Staff Green shield q8 ...".
     *
     * @param list<string> $segments
     * @return list<string>
     */
    private function splitExplicitWeaponFamilyBoundaries(array $segments): array
    {
        $out=[];
        $family='(?:staff|wand|focus|bow|longbow|sword|axe|hammer|shield|spear|scythe|daggers?)';
        foreach($segments as $segment){
            // Slash-separated market lists: split only when the right-hand side
            // clearly contains a weapon-family noun. Attribute lists such as
            // "Air Q9/11" therefore remain untouched.
            $parts=preg_split('/\s*\/\s*(?=[^\/]{0,80}\b'.$family.'\b)/iu',$segment) ?: [$segment];
            foreach($parts as $part){
                $part=trim($part);
                if($part==='') continue;

                $matches=$this->itemMatcher->matchAll($part);
                $concrete=array_values(array_filter($matches,fn(array $m):bool=>$this->taxonomy->isConcreteMatch($m)));
                if($concrete===[]){$out[]=$part;continue;}

                $first=$concrete[0];
                $firstEnd=(int)$first['start']+(int)$first['length'];
                $tail=mb_substr($part,$firstEnd);
                $itemFamily=$this->weaponFamilyFromName((string)$first['item']);
                if($itemFamily===null){$out[]=$part;continue;}

                // A later, different family noun is a hard ownership boundary.
                if(preg_match('/\b'.$family.'\b/iu',$tail,$fm,PREG_OFFSET_CAPTURE)){
                    $familyWord=mb_strtolower((string)$fm[0][0]);
                    $familyWord=rtrim($familyWord,'s');
                    if($familyWord==='dagger')$familyWord='dagger';
                    $normalizedItemFamily=rtrim(mb_strtolower($itemFamily),'s');
                    if($familyWord!==$normalizedItemFamily){
                        $offset=$firstEnd+(int)$fm[0][1];
                        // Include one descriptive token immediately before the
                        // family ("Green shield", "scarabshell shield") in the
                        // new clause where possible.
                        $prefix=mb_substr($part,0,$offset);
                        $boundary=$offset;
                        if(preg_match('/(?:^|[\s,;])([A-Za-z][A-Za-z\'’-]{2,})\s*$/u',$prefix,$pm,PREG_OFFSET_CAPTURE)){
                            $candidate=mb_strtolower((string)$pm[1][0]);
                            if(!preg_match('/^(?:q\d+|gold|blue|purple|white|inscribable|insc|os)$/iu',$candidate)){
                                $boundary=(int)$pm[1][1];
                            }
                        }
                        $left=trim(mb_substr($part,0,$boundary)," \t\n\r\0\x0B|,;/");
                        $right=trim(mb_substr($part,$boundary)," \t\n\r\0\x0B|,;/");
                        if($left!=='')$out[]=$left;
                        if($right!=='')$out[]=$right;
                        continue;
                    }
                }
                $out[]=$part;
            }
        }
        return $out;
    }

    private function weaponFamilyFromName(string $item): ?string
    {
        if(!preg_match('/\b(staff|wand|focus|bow|longbow|sword|axe|hammer|shield|spear|scythe|daggers?)\b/iu',$item,$m))return null;
        $f=mb_strtolower($m[1]);
        if($f==='longbow')return 'bow';
        return rtrim($f,'s');
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

        // Phase 2O: let an exact concrete catalog/component match win before the
        // category guard. Otherwise valid upgrades such as Wand Wrapping of the
        // Ritualist are discarded merely because "wand wrapping" is also a
        // generic component phrase.
        $preItems = $this->itemMatcher->matchAll($segment);

        // Phase 8A.1: in "11 zkeys for 7 ectos" the ectos are the price
        // currency, not a second item being sold. Restrict catalog ownership to
        // the left side when PriceMatcher recognizes an explicit quantity-total.
        $segmentPrice = $this->priceMatcher->parse($segment);
        if ($segmentPrice->basis === 'total' && preg_match('/^\s*\d+(?:[.,]\d+)?\s+(.+?)\s+for\s+\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?|ectos?|armbraces?)\b/iu', $segment, $forMatch)) {
            $leftItems = $this->itemMatcher->matchAll((string)$forMatch[1]);
            if ($leftItems !== []) $preItems = $leftItems;
        }

        // Phase 3C: compact same-q attribute lists represent multiple
        // market variants, e.g. "BDS q11 dom/air/FC/ES/comm".
        if ($preItems !== [] && !preg_match('/\bno\b/iu', $segment)) {
            if (preg_match('/\bq\s*(\d{1,2})\s+([A-Za-z]+(?:\s+[A-Za-z]+)?(?:\/[A-Za-z]+(?:\s+[A-Za-z]+)?)+)/u', $segment, $lm)) {
                $map = [
                    'dom'=>'Domination Magic','domination'=>'Domination Magic','air'=>'Air Magic',
                    'fc'=>'Fast Casting','es'=>'Energy Storage','comm'=>'Communing','com'=>'Communing',
                    'heal'=>'Healing Prayers','water'=>'Water Magic','insp'=>'Inspiration Magic','inspiration'=>'Inspiration Magic',
                    'earth'=>'Earth Magic','chann'=>'Channeling Magic','chan'=>'Channeling Magic','channeling'=>'Channeling Magic',
                    'divine'=>'Divine Favor','df'=>'Divine Favor','fire'=>'Fire Magic','death'=>'Death Magic',
                    'illu'=>'Illusion Magic','illusion'=>'Illusion Magic','resto'=>'Restoration Magic','restoration'=>'Restoration Magic',
                    'blood'=>'Blood Magic','sr'=>'Soul Reaping','smite'=>'Smiting Prayers','smiting'=>'Smiting Prayers','curses'=>'Curses','curs'=>'Curses',
                ];
                $attrs=[];
                foreach (array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*/u', $lm[2]) ?: []))) as $token) {
                    $k=mb_strtolower($token); if(isset($map[$k])) $attrs[$map[$k]]=true;
                }
                if (count($attrs) >= 2) {
                    $baseItem = null;
                    foreach ($preItems as $pi) {
                        if ($this->taxonomy->isConcreteMatch($pi)) { $baseItem = (string)$pi['item']; break; }
                    }
                    $baseItem ??= (string)$preItems[0]['item'];
                    $expanded = [];
                    foreach (array_keys($attrs) as $attributeName) {
                        $expanded = array_merge($expanded, $this->parseSegment($tradeType, $baseItem.' q'.$lm[1].' '.$attributeName));
                    }
                    if ($expanded !== []) return $expanded;
                }
            }
        }

        $hasConcretePreMatch = false;
        foreach ($preItems as $preItem) {
            if ($this->taxonomy->isConcreteMatch($preItem)) { $hasConcretePreMatch = true; break; }
        }
        $segmentClass = $this->reviewCandidateClassifier->classify(
            $this->tradeNotationCleaner->cleanItemCandidate($segment),
            $segment
        );
        if (!$hasConcretePreMatch && in_array($segmentClass['kind'], ['generic','service'], true)) return [];

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

        $items = $preItems;
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
                // Phase 3L.2: a fallback item with exactly one local money token
                // is still a concrete single-item quote. This covers learned /
                // newly seen items not yet present in items.json (e.g. Emerald
                // Blade = 15a) without trusting multi-price fragments.
                if ($price->basis === 'unqualified' && $price->ectoValue !== null) {
                    $moneyCount = preg_match_all('/(?<![a-z0-9])\d+(?:[.,]\d+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/iu', $segment);
                    if ($moneyCount === 1 && !preg_match('/\b(?:package|bundle|together)\b/iu', $segment)) {
                        $price = new ParsedPrice(
                            $price->amount,
                            $price->currency,
                            $price->ectoValue,
                            'each_inferred',
                            1.0,
                            $price->ectoValue,
                            $price->raw,
                        );
                    }
                }
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

        // Phase 3V: a package/bundle is a trade construction, not a fake item.
        // Keep every real catalog item visible, but never assign the total package
        // price to an individual item and never invent "Bundle: A + B" as an item.
        $wholePrice = $this->priceMatcher->parse($segment);
        if (count($items) > 1 && $wholePrice->amount !== null && preg_match('/\b(?:package|bundle|all unidentified|together)\b/i', $segment)) {
            $bundleOffers = [];
            $seen = [];
            foreach ($items as $item) {
                $key = (string)$item['key'];
                if (isset($seen[$key]) || !$this->taxonomy->isConcreteMatch($item)) continue;
                $seen[$key] = true;
                $mods = array_merge($this->modifierMatcher->match($segment), $this->metadataExtractor->extract($segment));
                $mods['bundle_total_amount'] = $wholePrice->amount;
                $mods['bundle_total_currency'] = $wholePrice->currency;
                $mods['bundle_member_count'] = count($items);
                $profileData = $this->profileResolver?->resolve($key, $item['category'] ?? 'unknown', $mods) ?? [
                    'profile'=>[], 'relevant'=>$mods, 'market_key'=>$key
                ];
                $bundleOffers[] = new ParsedOffer(
                    $tradeType,
                    (string)$item['item'],
                    $key,
                    $mods,
                    new ParsedPrice(null, null, null, 'bundle_total', null, null, $wholePrice->raw),
                    0.9,
                    'accepted',
                    'catalog_match',
                    $segment,
                    $this->tokenizer->tokenize($segment),
                    $profileData['profile'],
                    $profileData['relevant'],
                    $profileData['market_key'],
                );
            }
            if ($bundleOffers !== []) return $bundleOffers;
        }

        // Phase 3L: if several concrete items share a segment but the text has
        // fewer money quotes than item mentions, do not let the lone price bind
        // to whichever slice happens to contain it. Explicit bundles were
        // handled above; ordinary shared-price lists stay review-safe.
        $moneyTokenCount = preg_match_all('/(?<![a-z0-9])\d+(?:[.,]\d+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/iu', $segment);
        $slashSharedList = $moneyTokenCount === 1
            && !preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*\/\s*(?:ea|each|e|st|stk|stack)\b/iu', $segment)
            && preg_match('/[A-Za-z][^|;,]{0,40}\s\/\s[A-Za-z][^|;,]{0,40}(?:\s\/\s[A-Za-z][^|;,]{0,40})?/u', $segment);
        $compactCommodityList = $moneyTokenCount === 1
            && preg_match('/\b(?:warsupps?|war\s*supplies|eggs?|honeycombs?)\b/iu', $segment)
            && preg_match('/\b(?:cupcakes?|pumpkin\s+pie|slice\s+of\s+pumpkin\s+pie)\b/iu', $segment);
        $ambiguousSharedPrice = $wholePrice->amount !== null
            && (
                (count($items) > 1 && $moneyTokenCount > 0 && $moneyTokenCount < count($items))
                || $slashSharedList
                || $compactCommodityList
            );

        $offers = [];
        foreach ($items as $index => $item) {
            $start = $item['start'];
            $end = $items[$index + 1]['start'] ?? mb_strlen($segment);
            $slice = count($items) === 1
                ? trim($segment, " \t\n\r\0\x0B|,;")
                : trim(mb_substr($segment, $start, $end - $start), " \t\n\r\0\x0B|,;");
            if ($slice === '') $slice = $segment;
            $price = $this->priceMatcher->parse($slice);
            if ($price->amount === null && count($items) === 1 && $this->wholePriceIsLocalToItem($wholePrice, $segment, $slice, $start)) {
                $price = $wholePrice;
            }
            $price = $this->resolvePriceSemantics($price, $item, $slice);
            if ($ambiguousSharedPrice && $price->amount !== null) {
                $price = new ParsedPrice(
                    $price->amount,
                    $price->currency,
                    $price->ectoValue,
                    'uncertain',
                    $price->quantity,
                    null,
                    $price->raw,
                );
            }
            $setQuantity = $this->setResolver->resolve((string)$item['item'], $slice);
            if ($setQuantity !== null && $price->amount !== null) {
                $price = new ParsedPrice($price->amount,$price->currency,$price->ectoValue,'set',$setQuantity,$price->ectoValue!==null?$price->ectoValue/$setQuantity:null,$price->raw);
            }
            // Modifier ownership is item-local. When multiple catalogue items
            // share one chat segment, whole-segment metadata must never bleed
            // into each item (e.g. "BDS, Q8 Tac Shield" or
            // "Celestial Shield r8 Tact +10 Fire/-2we"). For a single item,
            // whole-segment context is still safe and preserves legacy syntax.
            $modifiers = count($items) === 1
                ? array_merge(
                    $this->modifierMatcher->match($segment),
                    $this->metadataExtractor->extract($segment),
                    $this->modifierMatcher->match($slice),
                    $this->metadataExtractor->extract($slice)
                )
                : array_merge(
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
            if (isset($modifiers['attribute']) && $this->attributeIsNegated($segment, (string)$modifiers['attribute'])) {
                unset($modifiers['attribute'], $modifiers['attribute_key']);
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


    /** Phase 3V: remove explicitly negated item families from parsing input. */
    private function stripNegativeItemClauses(string $text): string
    {
        // Parenthetical exclusions are extremely common in Kamadan:
        // "unid golds (no scythes, shields or spears) 1k each".
        $text = preg_replace('/\(\s*(?:no|except|excluding|without)\b[^)]*\)/iu', ' ', $text) ?? $text;
        // Also support short inline forms when they terminate at a strong separator.
        $text = preg_replace('/\b(?:except|excluding|without)\s+[^|;]+(?=\s*(?:\||;|$))/iu', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @param list<string> $segments @return list<string> */
    private function expandVariantClauses(array $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            $matches = $this->itemMatcher->matchAll($segment);
            $concrete = array_values(array_filter($matches, fn(array $m): bool => $this->taxonomy->isConcreteMatch($m)));
            if (count($concrete) !== 1 || !str_contains($segment, ',')) {
                $out[] = $segment;
                continue;
            }

            $item = $concrete[0];
            $tailStart = $item['start'] + $item['length'];
            $tail = trim(mb_substr($segment, $tailStart), " \t\n\r\0\x0B:,-");
            if (!preg_match('/\b(?:q|r|rq|req)\s*\d{1,2}\b/iu', $tail)) {
                $out[] = $segment;
                continue;
            }
            $parts = preg_split('/\s*,\s*(?=(?:q|r|rq|req)\s*\d{1,2}\b)/iu', $tail) ?: [];
            if (count($parts) < 2) {
                $out[] = $segment;
                continue;
            }
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') $out[] = (string)$item['item'].' '.$part;
            }
        }
        return $out;
    }

    /** @param list<string> $segments @return list<string> */
    private function inheritConcreteItemContext(array $segments): array
    {
        $out = [];
        $lastItem = null;
        foreach ($segments as $segment) {
            $matches = $this->itemMatcher->matchAll($segment);
            $concrete = array_values(array_filter($matches, fn(array $m): bool => $this->taxonomy->isConcreteMatch($m)));
            if ($concrete !== []) {
                $lastItem = (string)$concrete[0]['item'];
                $out[] = $segment;
                continue;
            }

            // A continuation clause normally starts with requirement/attribute or
            // a bare price. Do not inherit into prose/services or obvious new nouns.
            $continuation = (bool)preg_match('/^(?:q|r|rq|req)\s*\d{1,2}\b|^(?:fc|es|sr|df|dom|inspa?|inspiration|comm?|communing|motivation|tact?|tactics|str|strength|prot|heal|water|air|fire|earth)\b/iu', trim($segment));
            // Never inherit a previous concrete skin into a clause that names an
            // explicit weapon family of its own (e.g. BDS -> "Q8 Tac Shield").
            $hasExplicitWeaponFamily = (bool)preg_match('/\b(?:staff|wand|focus|bow|sword|axe|hammer|shield|spear|scythe|daggers?)s?\b/iu', $segment);
            if ($lastItem !== null && $continuation && !$hasExplicitWeaponFamily) {
                $out[] = $lastItem.' '.trim($segment);
            } else {
                $out[] = $segment;
                if ($hasExplicitWeaponFamily) $lastItem = null;
            }
        }
        return $out;
    }

    /**
     * Phase 3D: a price found elsewhere in a larger segment must not leak into
     * an item slice. The only safe fallback is a price immediately before the
     * matched item, e.g. "30a BDS".
     */
    private function wholePriceIsLocalToItem(ParsedPrice $wholePrice, string $segment, string $slice, int $itemStart): bool
    {
        if ($wholePrice->amount === null || $wholePrice->raw === null) return false;
        if (mb_stripos($slice, $wholePrice->raw) !== false) return true;
        $pricePos = mb_stripos($segment, $wholePrice->raw);
        if ($pricePos === false || $pricePos >= $itemStart) return false;
        $between = mb_substr($segment, $pricePos + mb_strlen($wholePrice->raw), $itemStart - ($pricePos + mb_strlen($wholePrice->raw)));
        return mb_strlen($between) <= 12 && (bool)preg_match('/^[\s:;,.\-|\/]*$/u', $between);
    }

    /**
     * Phase 3D: turn syntactic prices into market-safe price observations.
     * Bare weapon prices are naturally per-item. Currency commodities are more
     * conservative: suspicious/unqualified armbrace prices are kept visible as
     * raw prices but excluded from unit statistics.
     */
    private function resolvePriceSemantics(ParsedPrice $price, array $item, string $slice): ParsedPrice
    {
        if ($price->amount === null) return $price;

        $market = MarketSemantics::fromItem($item);
        // PriceMatcher recognizes explicit stack wording syntactically. 3H owns
        // the actual stack size here so future non-250 quote units do not require
        // regex changes. Multi-stack totals preserve their detected stack count.
        if ($market->isStackQuoted() && in_array($price->basis, ['stack','stack_total'], true)) {
            $quantity = $market->quoteSize;
            if ($price->basis === 'stack_total' && $price->quantity !== null && $price->quantity > 0) {
                // PriceMatcher currently reports item quantity using a 250 base.
                $stackCount = $price->quantity / 250.0;
                $quantity = $stackCount * $market->quoteSize;
            }
            return new ParsedPrice(
                $price->amount,
                $price->currency,
                $price->ectoValue,
                $price->basis,
                $quantity,
                $price->ectoValue !== null && $quantity > 0 ? $price->ectoValue / $quantity : null,
                $price->raw,
            );
        }

        if ($price->basis !== 'unqualified') return $price;

        $key = (string)($item['key'] ?? '');
        $category = (string)($item['category'] ?? '');
        $ecto = $price->ectoValue;

        // Phase 3H: market quote semantics are catalog-owned. A known
        // stack-quoted item may omit `stk` in Kamadan; only that item's declared
        // quote size is used. No category-wide consumable assumption is made.
        if ($market->isStackQuoted()) {
            return new ParsedPrice(
                $price->amount,
                $price->currency,
                $ecto,
                'stack_inferred',
                $market->quoteSize,
                $ecto !== null ? $ecto / $market->quoteSize : null,
                $price->raw,
            );
        }

        // Phase 3L: explicit catalog metadata can also declare that bare
        // Kamadan quotes are per item. This is intentionally item-specific:
        // currencies/consumables are never promoted category-wide.
        if ($market->isEachQuoted()) {
            return new ParsedPrice(
                $price->amount,
                $price->currency,
                $ecto,
                'each_inferred',
                1.0,
                $ecto,
                $price->raw,
            );
        }

        // Phase 3L.1: Kamadan Conset convention is currency-sensitive.
        // Bare ecto quotes are per conset ("Conset 2e"), while bare armbrace
        // quotes represent a full stack ("Consets 9a"). Explicit /ea or /stk
        // syntax has already been handled by PriceMatcher and never reaches
        // this branch.
        if ($key === 'conset') {
            if ($price->currency === 'e') {
                return new ParsedPrice(
                    $price->amount,
                    $price->currency,
                    $ecto,
                    'each_inferred',
                    1.0,
                    $ecto,
                    $price->raw,
                );
            }
            if ($price->currency === 'a') {
                $quantity = 250.0;
                return new ParsedPrice(
                    $price->amount,
                    $price->currency,
                    $ecto,
                    'stack_inferred',
                    $quantity,
                    $ecto !== null ? $ecto / $quantity : null,
                    $price->raw,
                );
            }
        }

        if ($key === 'armbrace-of-truth') {
            // Armbraces are normally quoted in ecto per armbrace. Bare armbrace
            // currency prices ("17a") or extreme bare ecto totals ("250e") are
            // ambiguous and must not become a per-unit market datapoint.
            $safeUnit = $price->currency === 'e' && $price->amount !== null && $price->amount > 0 && $price->amount <= 100;
            return new ParsedPrice(
                $price->amount,
                $price->currency,
                $ecto,
                $safeUnit ? 'each_inferred' : 'uncertain',
                $price->quantity,
                $safeUnit ? $ecto : null,
                $price->raw,
            );
        }

        // Concrete non-commodity items are conventionally priced per item when
        // a single bare money amount follows the item name (BDS 30a, VS 5a...).
        if (!in_array($category, ['currency','material','consumable'], true)) {
            return new ParsedPrice($price->amount,$price->currency,$ecto,'each_inferred',$price->quantity,$ecto,$price->raw);
        }

        // For other commodities, an explicit xN after the price is already
        // classified as `each` by PriceMatcher. A naked amount remains visible
        // but is not trusted for statistics unless context explicitly says each.
        return new ParsedPrice($price->amount,$price->currency,$ecto,'uncertain',$price->quantity,null,$price->raw);
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

    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function promoteExplicitGenericRequirements(array $offers): array
    {
        return array_map(function (ParsedOffer $offer): ParsedOffer {
            if ($offer->status !== 'review' || $offer->reason !== 'low_confidence') return $offer;
            if (!$this->taxonomy->isGenericName($offer->item)) return $offer;
            if (!preg_match('/\b(?:q|r|req)\s*\d{1,2}\b/iu', $offer->segment) && !isset($offer->modifiers['requirement'])) return $offer;
            return new ParsedOffer(
                $offer->tradeType, $offer->item, $offer->itemKey, $offer->modifiers, $offer->price,
                max(0.86, $offer->confidence), 'accepted', 'catalog_match', $offer->segment,
                $offer->tokens, $offer->profile, $offer->relevantProperties, $offer->marketKey, $offer->exchange
            );
        }, $offers);
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

            // Phase 2P: learned modifier words can exist as catalog rows from old
            // review decisions. In an explicit weapon-mod advertisement they are
            // context, not standalone market items.
            $itemLower = mb_strtolower(trim($offer->item));
            if (in_array($itemLower, ['cruel','shocking','shock','defense','enchanting'], true)
                && preg_match('/\b(?:spear|bow|axe|sword|staff|scythe|hammer|daggers?)\b.*(?:[/,]|\bmods?\b)/iu', $offer->segment)) {
                return false;
            }
            if (!$this->taxonomy->isGenericName($offer->item)) return true;

            $generic = mb_strtolower(trim($offer->item));
            $segment = mb_strtolower(trim($offer->segment));

            // Generic collector buckets never form a useful price observation by
            // themselves. In the live queue they are shadow rows next to a concrete
            // mini/green, or broad category prose with no single item price.
            if (in_array($generic, ['miniature','unique item'], true)) return false;

            // Upgrade/component requests must not become the base weapon family.
            if (preg_match('/\b(?:wra(?:p(?:ping)?)?|head|haft|grip|string|bowstring|pommel|core|mod|mods|vamp|zealous|swift|hale|patron|insightful|sundering|furious)\b/iu', $segment)) {
                return false;
            }

            // Bare family rows cut out of a comma-separated multi-family list with
            // no price are category prose, not a price observation.
            if ($offer->price->amount === null
                && preg_match('~\b(?:sword|staff|staves|daggers?|axe|axes|bow|bows|spear|scythe|hammer|wand|focus|shield)s?\b[^|]*[,/]\s*(?:sword|staff|staves|daggers?|axe|axes|bow|bows|spear|scythe|hammer|wand|focus|shield)~iu', $segment)) {
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

    /**
     * Phase 2Q: accepted catalog matches below 0.85 are still seeded into the
     * review queue. Promote well-defined generic market searches so legitimate
     * requests such as Q8 Bows, Q9 Wands and generic Sword collection searches
     * stop consuming manual review capacity.
     *
     * @param list<ParsedOffer> $offers
     * @return list<ParsedOffer>
     */
    private function promoteExplicitGenericMarketSearches(array $offers, string $fullMessage): array
    {
        return array_map(function (ParsedOffer $offer) use ($fullMessage): ParsedOffer {
            if ($offer->status !== 'accepted' || $offer->reason !== 'catalog_match' || $offer->confidence >= 0.85) return $offer;
            if (!$this->taxonomy->isGenericName($offer->item)) return $offer;

            $segment = mb_strtolower($offer->segment);
            $generic = mb_strtolower(trim($offer->item));
            // Component/mod advertisements mention a weapon family as the target
            // of the upgrade. They are not generic weapon-market observations.
            if ($this->isUpgradeTargetContext($segment) || $this->isUpgradeTargetContextForFamily($fullMessage, $generic)) return $offer;
            $explicitGeneric = false;
            $context = mb_strtolower($fullMessage . ' ' . $segment);
            if (preg_match('/\b(?:q|r|req)\s*\d{1,2}\b/iu', $context)) $explicitGeneric = true;
            if (preg_match('/\b(?:many|all|any|collection|white|minis?|weapons?|staves|wands|bows|axes|swords|shields|daggers|spears)\b/iu', $context)) $explicitGeneric = true;
            if ($offer->price->amount !== null && preg_match('/\b'.preg_quote($generic, '/').'s?\b/iu', $context)) $explicitGeneric = true;

            if (!$explicitGeneric) return $offer;
            return new ParsedOffer(
                $offer->tradeType, $offer->item, $offer->itemKey, $offer->modifiers, $offer->price,
                0.86, 'accepted', 'catalog_match', $offer->segment,
                $offer->tokens, $offer->profile, $offer->relevantProperties, $offer->marketKey, $offer->exchange
            );
        }, $offers);
    }

    /**
     * Phase 2Q: suppress generic catalog rows even when they are technically
     * accepted. The review repository seeds every offer below 0.85, so an 0.84
     * Staff next to Plagueborn Staff still becomes manual review. Concrete item
     * identity always wins over a family/category shadow.
     *
     * @param list<ParsedOffer> $offers
     * @return list<ParsedOffer>
     */
    private function suppressGenericCatalogShadows(array $offers, string $fullMessage): array
    {
        return array_values(array_filter($offers, function (ParsedOffer $offer) use ($offers, $fullMessage): bool {
            if (!in_array($offer->reason, ['catalog_match','low_confidence'], true)) return true;

            $itemLower = mb_strtolower(trim($offer->item));
            $segment = mb_strtolower(trim($offer->segment));

            // Learned modifier adjectives are not standalone items when a concrete
            // skin or weapon is present (e.g. Fiery Chaos Axe).
            if (in_array($itemLower, ['fiery','icy','cruel','shocking','shock','defense','enchanting'], true)) {
                foreach ($offers as $other) {
                    if ($other === $offer || $this->taxonomy->isGenericName($other->item)) continue;
                    if ($other->status === 'accepted' && $other->itemKey !== $offer->itemKey) return false;
                }
            }

            if (!$this->taxonomy->isGenericName($offer->item)) return true;

            // A weapon family inside an upgrade/component request is merely the
            // target type: +30hp for Staff, Zealous for Spear, Bowstring, etc.
            if ($this->isUpgradeTargetContextForFamily($fullMessage, $itemLower) || $this->isUpgradeTargetContext($segment)) return false;

            $generic = $itemLower;

            // Concrete matches from the same message/parse outrank generic family
            // or category rows. This catches Miniature -> Kuuna/Dhuum/Rift Warden,
            // Staff -> Plagueborn/Outcast/BDS, Focus item -> Bogroot Focus, etc.
            foreach ($offers as $other) {
                if ($other === $offer || $other->status !== 'accepted') continue;
                if ($this->taxonomy->isGenericName($other->item)) continue;
                if ($other->itemKey === $offer->itemKey) continue;

                $otherItem = mb_strtolower(trim($other->item));
                $otherSegment = mb_strtolower(trim($other->segment));
                $sameContext = $segment === $otherSegment
                    || str_contains($segment, $otherSegment)
                    || str_contains($otherSegment, $segment);

                $familyRelated = $this->concreteBelongsToGeneric($otherItem, $generic);
                if ($familyRelated && $sameContext) return false;
            }

            // Collector/category buckets are useful only when the trader is truly
            // asking for the category itself (e.g. "Unded White Minis"). If the
            // segment names a concrete mini/green elsewhere in the parse, remove it.
            if (in_array($generic, ['miniature','unique item','focus item'], true)) {
                foreach ($offers as $other) {
                    if ($other === $offer || $other->status !== 'accepted' || $this->taxonomy->isGenericName($other->item)) continue;
                    $otherItem = mb_strtolower($other->item);
                    if ($this->concreteBelongsToGeneric($otherItem, $generic)) return false;
                }
            }

            // Keep true generic requests. Review-level generic shadows that survived
            // all rules above retain the old 2N behavior.
            if ($offer->status === 'review' && $offer->reason === 'low_confidence') {
                if (in_array($generic, ['miniature','unique item'], true)) return false;
                if ($offer->price->amount === null
                    && preg_match('~\b(?:sword|staff|staves|daggers?|axe|axes|bow|bows|spear|scythe|hammer|wand|focus|shield)s?\b[^|]*[,/]\s*(?:sword|staff|staves|daggers?|axe|axes|bow|bows|spear|scythe|hammer|wand|focus|shield)~iu', $segment)) return false;
            }
            return true;
        }));
    }

    private function isUpgradeTargetContext(string $segment): bool
    {
        $patterns = [
            '/\b(?:staff\s+(?:head|wrap(?:ping)?)|wand\s+(?:wrap(?:ping)?|memory)|bow\s*(?:string|sr)|bowstring|(?:spear|axe|hammer|scythe)\s+(?:head|haft|grip|wrap(?:ping)?))\b/iu',
            '/\+\s*\d+\s*(?:hp|sr|crit|energy)?\s+(?:for|to)\s+(?:staff|spear|bow|axe|sword|scythe)\b/iu',
            '/\b(?:vamp|vampiric|zealous|sundering|furious|enchant(?:ing)?|insightful|def(?:ense)?|armor\s*pen)\b[^|]{0,24}\b(?:staff|spear|bow|axe|sword|scythe|hammer)\b/iu',
            '/\b(?:staff|spear|bow|axe|sword|scythe|hammer)\b[^|]{0,24}\b(?:vamp|vampiric|zealous|sundering|furious|enchant(?:ing)?|insightful|def(?:ense)?|armor\s*pen)\b/iu',
        ];
        foreach ($patterns as $pattern) if (preg_match($pattern, $segment)) return true;
        return false;
    }

    private function isUpgradeTargetContextForFamily(string $message, string $generic): bool
    {
        $m = mb_strtolower($message);
        $family = match ($generic) {
            'staff' => 'staff|staves', 'wand' => 'wand|wands', 'bow' => 'bow|bows',
            'axe' => 'axe|axes', 'sword' => 'sword|swords', 'spear' => 'spear|spears',
            'scythe' => 'scythe|scythes', 'hammer' => 'hammer|hammers', default => preg_quote($generic, '/'),
        };

        // Direct target phrases: +30hp for Staff, Zealous for Spear, Vamp Bow.
        if (preg_match('/\+\s*\d+\s*(?:hp|sr|crit|energy)?\s+(?:for|to)\s+(?:'.$family.')\b/iu', $m)) return true;
        if (preg_match('/\b(?:vamp|vampiric|zealous|sundering|furious|enchant(?:ing)?|insightful|def(?:ense)?|armor\s*pen)\b[^|,;]{0,22}\b(?:'.$family.')\b/iu', $m)) return true;

        // Component-list shorthand: Bow/Axe/Spear Grip of Defense. A family token
        // anywhere in the slash-list is a component target, not a base weapon offer.
        if (preg_match('/\b(?:bow|axe|spear|sword|staff|scythe|hammer)(?:\s*\/\s*(?:bow|axe|spear|sword|staff|scythe|hammer))+\s+(?:grip|haft|head|wrap(?:ping)?|string|bowstring|pommel)\b/iu', $m)
            && preg_match('/\b(?:'.$family.')\b/iu', $m)) return true;

        if ($generic === 'staff' && preg_match('/\bstaff\s+(?:head|wrap(?:ping)?)\b/iu', $m)) return true;
        if ($generic === 'wand' && preg_match('/\bwand\s+(?:wrap(?:ping)?|memory)\b/iu', $m)) return true;
        if ($generic === 'bow' && preg_match('/\bbow\s*(?:string|sr)\b|\bbowstring\b/iu', $m)) return true;
        if ($generic === 'spear' && preg_match('/\bspear\s+(?:heads?|wrap(?:ping)?s?|grips?)\b/iu', $m)) return true;
        if ($generic === 'sword' && preg_match('/\+\s*\d+\s*(?:sr|crit|energy|hp)?\s+sword\s+of\b/iu', $m)) return true;
        if ($generic === 'axe' && preg_match('/\+\s*30\s*hp\s+for\s+staff\s*[.;,]\s*axe\b/iu', $m)) return true;
        return false;
    }

    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function promotePhase2RTrustedCatalogMatches(array $offers, string $fullMessage): array
    {
        return array_map(function (ParsedOffer $offer) use ($fullMessage): ParsedOffer {
            if ($offer->reason !== 'catalog_match' || $offer->confidence >= 0.85) return $offer;
            $item = mb_strtolower(trim($offer->item));
            $segment = mb_strtolower(trim($offer->segment));

            // Final observed trusted aliases/canonical identities from the 2Q queue.
            if ($item === 'voltaic spear' && preg_match('/\b(?:volta|voltaic spear)\b/iu', $segment.' '.$fullMessage)) {
                return $this->withConfidence($offer, 0.90);
            }
            if ($item === 'tall shield' && preg_match('/\b(?:q|r)\s*\d{1,2}\b[^|]{0,24}\b(?:tall|tall shield)\b/iu', $fullMessage)) {
                return $this->withConfidence($offer, 0.90);
            }
            if (in_array($item, ['wand','staff'], true)
                && preg_match('/\b(?:sr|soul\s*reaping)\s+(?:wand|staff)\b/iu', $fullMessage)) {
                return $this->withConfidence($offer, 0.86);
            }
            return $offer;
        }, $offers);
    }

    private function withConfidence(ParsedOffer $offer, float $confidence): ParsedOffer
    {
        return new ParsedOffer(
            $offer->tradeType, $offer->item, $offer->itemKey, $offer->modifiers, $offer->price,
            $confidence, 'accepted', 'catalog_match', $offer->segment,
            $offer->tokens, $offer->profile, $offer->relevantProperties, $offer->marketKey, $offer->exchange
        );
    }

    private function concreteBelongsToGeneric(string $concrete, string $generic): bool
    {
        $map = [
            'staff' => ['staff','stave'], 'wand' => ['wand','scepter','cane'], 'bow' => ['bow'],
            'axe' => ['axe'], 'sword' => ['sword','blade','edge'], 'spear' => ['spear'],
            'shield' => ['shield','aegis','buckler'], 'focus item' => ['focus','chakram','idol','prism','offhand'],
            'miniature' => ['miniature','kuuna','dhuum','destroyer','mallyx','rift warden','ghostly hero','gray giant','grey giant','mox','salma','evennia','polar bear','mad king'],
            'unique item' => ['bogroot','green','unique'],
            'daggers' => ['dagger'], 'scythe' => ['scythe'], 'hammer' => ['hammer'],
        ];
        foreach (($map[$generic] ?? [$generic]) as $needle) {
            if (str_contains($concrete, $needle)) return true;
        }
        return false;
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

    private function attributeIsNegated(string $text, string $attribute): bool
    {
        if (!preg_match_all('/\bno\b([^|,;)]*)/iu', $text, $matches)) return false;
        $aliases = [mb_strtolower($attribute)];
        $aliases = array_merge($aliases, match (mb_strtolower($attribute)) {
            'channeling magic' => ['chann','chan','channeling'],
            'curses' => ['curs','curse','curses'],
            'smiting prayers' => ['smite','smiting'],
            'domination magic' => ['dom','domination'],
            'fast casting' => ['fc','fast casting'],
            'energy storage' => ['es','energy storage'],
            'soul reaping' => ['sr','soul reaping'],
            'restoration magic' => ['resto','restoration'],
            'illusion magic' => ['illu','illus','illusion'],
            default => [],
        });
        foreach ($matches[1] as $negative) {
            $normalized = ' '.mb_strtolower((string)$negative).' ';
            foreach ($aliases as $alias) {
                if (preg_match('/(?:^|[^a-z])'.preg_quote($alias,'/').'(?:$|[^a-z])/iu', $normalized)) return true;
            }
        }
        return false;
    }

}
