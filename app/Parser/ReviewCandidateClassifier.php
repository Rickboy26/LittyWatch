<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Final guard before an unmatched fallback is allowed into Parser Review.
 * It separates concrete item candidates from category searches, services,
 * price fragments and pure modifier/context text.
 */
final class ReviewCandidateClassifier
{
    /** @return array{kind:string,reason:string} */
    public function classify(string $candidate, string $segment): array
    {
        $c = trim(mb_strtolower($candidate));
        $s = trim(mb_strtolower($segment));

        if ($c === '' || $c === 'unknown') return ['kind'=>'noise','reason'=>'empty'];
        if (!preg_match('/[\p{L}\p{N}]/u', $c)) return ['kind'=>'noise','reason'=>'punctuation'];

        // Pure currency/price leftovers are never item names.
        if (preg_match('/^(?:\d+(?:[.,]\d+)?\s*)?(?:k|e|a|g|zkeys?|100k)(?:\s*x\s*\d+)?$/iu', $c)) {
            return ['kind'=>'noise','reason'=>'price_fragment'];
        }
        if (preg_match('/^[a-z]$/iu', $c)) return ['kind'=>'noise','reason'=>'single_token_fragment'];

        // Category-level searches are useful market intent, but they are not
        // concrete catalog items and must not pollute no_catalog_item review.
        if (preg_match('/\b(?:old\s*school|os)\b.*\b(?:weapons?|shields?)\b|\b(?:weapons?|shields?)\b.*\b(?:old\s*school|os)\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_weapon_category'];
        }
        if (preg_match('/^weapons?\s*&\s*shields?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_weapon_category'];
        }
        if (preg_match('/^(?:weapons?|shields?|mods?|skins?|greens?|tomes?)$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_category'];
        }
        if (preg_match('/\bhero\s+armor\s+upgrades?\b|\bmods?\s*\/\s*inscribable\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_upgrade_category'];
        }

        // Residual review phrases that describe a build/stat family rather than
        // one concrete inventory item. Keep them out of no_catalog_item.
        if (preg_match('/^(?:dom(?:ination)?|heal(?:ing)?|prot(?:ection)?|smite|fc|sr|df|spaw(?:ning)?|resto(?:ration)?|com(?:muning)?|inspi(?:ration)?|illu(?:sion)?)\s+sets?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'attribute_set_category'];
        }
        if (preg_match('/\b(?:for|on)\s+(?:scy(?:the)?|staff|wand|shield|sword|axe|bow|spear|daggers?)\b/iu', $c)
            && preg_match('/\b(?:elementalist|warrior|ranger|monk|necromancer|mesmer|assassin|ritualist|paragon|dervish)\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'profession_weapon_modifier_search'];
        }

        // Services / non-item requests can still be market chat, but cannot be
        // represented as an item price point in the current data model.
        if (preg_match('/\b(?:towns?\s+in\s+proph|rp\s+to\s+trim\s+your\s+guild|storage\s+sale|inventory\s+list)\b/iu', $s)) {
            return ['kind'=>'service','reason'=>'service_or_listing'];
        }
        if (preg_match('/\bcan\s+someone\s+help\s+me\s+get\b/iu', $s)) {
            return ['kind'=>'service','reason'=>'non_trade_request'];
        }

        // Points are a title-point abstraction, not one concrete inventory item.
        if (preg_match('/\b(?:party|drunk(?:ard)?)\s+points?\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'points_category'];
        }

        // Leftovers composed only of requirement/attribute/modifier language.
        $rest = preg_replace('/\b(?:q|r|rq|req(?:uirement)?)\s*\d{0,2}(?:\s*[-\/]\s*\d{1,2})?\b/iu', ' ', $c) ?? $c;
        $rest = preg_replace('/\b(?:es|fc|sr|df|spaw(?:ning)?|dom(?:ination)?|illu(?:sion)?|inspi(?:ration)?|heal(?:ing)?|smite|smiting|fire|water|air|earth|blood|death|curs(?:es)?|resto(?:ration)?|com(?:muning)?|chan(?:neling)?|str(?:ength)?|tac(?:tics)?|lead(?:ership)?)\b/iu', ' ', $rest) ?? $rest;
        $rest = preg_replace('/\b(?:insc(?:r(?:ibable|iptable)?)?|inscribable|os|old\s*school|unid(?:entified)?|unded(?:icated)?|ded(?:icated)?|gold|rare|purple|blue|white|req|mod|mods)\b/iu', ' ', $rest) ?? $rest;
        $rest = preg_replace('/[\d\s.,:;!?.=_+\-\/|()\[\]"]+/u', '', $rest) ?? $rest;
        if ($rest === '') return ['kind'=>'noise','reason'=>'modifier_only'];

        return ['kind'=>'item','reason'=>'concrete_candidate'];
    }
}
