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
        if (preg_match('/^(?:weapons?|shields?|mods?|skins?|greens?|tomes?|tonics?|bowstrings?|inscriptions?|upgrades?)$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_category'];
        }
        // Phase 2K: remaining fragments and upgrade-family advertisements.
        if (preg_match('/^(?:\+?\d+%?\s*)?\(?\s*(?:ench|en|enchant|stance|stanc)(?:\s*\/\s*(?:ench|en|enchant|stance|stanc))*\s*\)?(?:\s*\/\s*15\^50)?(?:\s*\d+[ekag])?$/iu', $c)
            || preg_match('/^15\s+(?:en|stanc(?:e)?)\s+\d+(?:[.,]\d+)?[ekag]$/iu', $c)
            || preg_match('/^15%?\s*\((?:ench|enchant|stance|stanc)(?:\/(?:ench|enchant|stance|stanc))?$/iu', $c)) {
            return ['kind'=>'noise','reason'=>'orphan_modifier_fragment'];
        }
        if (preg_match('/\bsweet\s+points?\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'title_points_category'];
        }
        // Use the original segment too: the cleaned item candidate may have already
        // collapsed "staff heads" or "wand wrapping" to the base weapon token.
        if (preg_match('/\b(?:staff\s+heads?|wand\s+wrapp?ings?|bowstrings?)\b/iu', $s)
            || preg_match('/\b(?:bow|axe|spear|scythe|staff|wand)\s+mods?\b/iu', $s)
            || preg_match('/\+\s*30\s*hp\s+for\s+(?:staff|axe|scythe|bow|spear|sword|wand)/iu', $s)
            || preg_match('/\b(?:vampiric|zealous|mastery|cruel|shocking|def(?:ense)?|ench(?:ant)?)\s+(?:for\s+)?(?:bow|axe|spear|staff|wand|scythe)\b/iu', $s)
            || preg_match('/\b(?:bow|spear)\s+(?:def|ench|cruel|shock|zealous|vamp|mastery)(?:[\/,. ]+(?:def|ench|cruel|shock|zealous|vamp|mastery))*\b/iu', $s)) {
            return ['kind'=>'generic','reason'=>'weapon_upgrade_advertisement'];
        }
        if (preg_match('/^(?:icy|fiery|crippling|barbed|cruel)(?:[\s.,\/|]+(?:icy|fiery|crippling|barbed|cruel))*$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'upgrade_modifier_list'];
        }

        // Phase 2J: residual market-family/stat fragments seen after 2I.
        if (preg_match('/^(?:normal\s+)?tomes?(?:\s+\d+(?:[.,]\d+)?[gek]\/?(?:ea)?)?(?:\s+no\s+\w+)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'tome_category'];
        }
        if (preg_match('/^(?:mods?\s+)?(?:[+\-]?\d+(?:%|\^\d+)?(?:\s*vs\s*\w+)?(?:\s*[,\/|]\s*)?)+$/iu', $c)
            || (str_starts_with($c, 'mods ') && preg_match('/(?:\+\d|\d+%|vs\s+\w+)/iu', $c))) {
            return ['kind'=>'generic','reason'=>'modifier_bundle'];
        }
        if (preg_match('/^(?:\+?5\s+)?strength\s+of\s+the\s+warrior$/iu', $c)
            || preg_match('/^(?:mod\s+)?soul\s+reaping\s*\+?5\s+sta(?:ff)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'upgrade_or_stat_search'];
        }
        if (preg_match('/^(?:vs\s+q\d+|q\d+\s+(?:tac|str|lead|mot|comm|resto|fc|es)(?:\s+.*)?|(?:tac|str|lead)\s*\([^)]*\)\s*\w*)$/iu', $c)) {
            return ['kind'=>'noise','reason'=>'orphan_weapon_stats'];
        }
        if (preg_match('/^(?:elit|elite)\s+(?:w|mk|mo|r|n|me|e|a|rt|p|d)(?:\s*[,\/]\s*(?:w|mk|mo|r|n|me|e|a|rt|p|d))*(?:\s+ea.*)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'elite_tome_shorthand_family'];
        }
        if (preg_match('/^(?:party|rocks?|red|blue|green)$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'market_category_or_variant'];
        }

        // Phase 2I: upgrade components are not the underlying weapon.
        if (preg_match('/\b(?:staff\s+(?:head|wrapp?ing)|wand\s+wrapp?ing|(?:bow|axe|hammer|spear|scythe)\s+(?:grip|haft|string)|sword\s+pommel)\b/iu', $c)
            || preg_match('/\b(?:insightful|hale|zealous|vampiric|sundering|crippling|barbed|icy|fiery|cruel)\b.*\b(?:head|grip|haft|string|wrapp?ing|pommel)\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'weapon_upgrade_component'];
        }
        if (preg_match('/^(?:all\s+)?(?:celestial\s+)?minis?(?:atures?)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'miniature_category'];
        }

        if (preg_match('/\bhero\s+armor\s+upgrades?\b|\bmods?\s*\/\s*inscribable\b/iu', $c)) {
            return ['kind'=>'generic','reason'=>'generic_upgrade_category'];
        }

        if (preg_match('/^(?:mods?\s+)?(?:soul\s+reaping|spawning\s+power|fast\s+casting|domination|inspiration|illusion|death\s+magic|blood\s+magic|fire\s+magic|air\s+magic|earth\s+magic|water\s+magic)(?:\s+[+f]\s*\d*|\s+f)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'attribute_upgrade_search'];
        }
        if (preg_match('/^\d{1,2}%\s+mods?$/iu', $c) || preg_match('/^bowstrings?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'upgrade_family'];
        }
        if (preg_match('/^(?:strength\s+of\s+the\s+warrior|soul\s+reaping\s*\+?\s*5(?:\s+sta(?:ff)?)?)$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'upgrade_or_stat_search'];
        }
        if (preg_match('/^(?:\d+(?:[.,]\d+)?\s*[ekag]\s*)?(?:\(\s*\d+\s+times?\s*\)|\d+\s+times?)$/iu', $c)
            || preg_match('/^(?:each|ea)\s+(?:fro|from)?$/iu', $c)) {
            return ['kind'=>'noise','reason'=>'price_context_fragment'];
        }
        if (preg_match('/^(?:tormented|zodiac|celestial)\s+weapons?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'weapon_family_search'];
        }
        if (preg_match('/^(?:gold\s+)?unids?(?:\s+per)?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'unidentified_item_category'];
        }
        if (preg_match('/^\d+\s*point\s+alcohol\s+stacks?$/iu', $c) || preg_match('/^point\s+alcohol\s+stacks?$/iu', $c)) {
            return ['kind'=>'generic','reason'=>'alcohol_point_category'];
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
