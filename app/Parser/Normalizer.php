<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class Normalizer
{
    public function normalize(string $message): string
    {
        // LITTYWATCH_PHASE8D2C12_GOTT_SUFFIX_QUANTITY
        // GoTT5=11e -> GoTT 5=11e
        $message = preg_replace(
            '/\b(GoTT)(\d+)\b/iu',
            '$1 $2',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8D2C11_PRICE_JOINED_TRADE_MARKER
        // Compact Kamadan text can join a price directly to a new trade marker:
        // 11eWtbUnded mini oni -> 11e WtbUnded mini oni
        // 8D.2C9 then restores the boundary between WTB and Unded.
        $message = preg_replace(
            '/(\d+(?:[.,]\d+)?\s*(?:a|e|k))(?=(?:WTB|WTS|WTT)(?:Unded(?:icated)?|Ded(?:icated)?)\b)/iu',
            '$1 ',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8D2C9_COMPACT_TRADE_DEDICATION
        // Kamadan sometimes joins the trade marker directly to dedication:
        // WtbUnded mini oni -> WTB Unded mini oni
        $message = preg_replace(
            '/\b(WTB|WTS|WTT)(?=(?:Unded(?:icated)?|Ded(?:icated)?)\b)/iu',
            '$1 ',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8E_PROVEN_MARKET_SHORTHAND
        // Only proven GW/Kamadan shorthand. Keep these conservative.

        // Consumables.
        $message = preg_replace('/\bPbeacons?\b/iu', 'Party Beacon', $message) ?? $message;
        $message = preg_replace('/\bDcakes?\b/iu', 'Delicious Cake', $message) ?? $message;

        // "BU stacks" = Essence of Celerity stacks.
        // Do not replace bare "bu", which could be unrelated text.
        $message = preg_replace(
            '/\bBU\s+stacks?\b/iu',
            'Essence of Celerity',
            $message
        ) ?? $message;

        // Gift of the Huntsman.
        // Require a leading quantity so GotH/Gothic weapon/skin notation is
        // not accidentally interpreted as this consumable.
        $message = preg_replace(
            '/\b(\d+)\s+GOTHs?\b/iu',
            '$1 Gift of the Huntsman',
            $message
        ) ?? $message;

        // GoTT followed immediately by an ecto price:
        // GoTT2e / GoTT2.5e -> GoTT 2e / GoTT 2.5e.
        //
        // This intentionally does NOT touch GoTT5=11e. Phase 8D.2C12
        // handles that as quantity 5.
        $message = preg_replace(
            '/\b(GoTT)(\d+(?:[.,]\d+)?e)\b/iu',
            '$1 $2',
            $message
        ) ?? $message;

        // Voltaic Spear shorthand.
        // Never replace generic "vs". Only the proven requirement forms.
        $message = preg_replace(
            '/\b((?:q|r)\s*\d{1,2}\s+)VS\b/iu',
            '$1Voltaic Spear',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/\bVS(?=\s+(?:q|r)\s*\d{1,2}\b)/u',
            'Voltaic Spear',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/\bany\s+q\s+VS\b/u',
            'any q Voltaic Spear',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8F3_BO_STAFF_STAT_ORDER
        // BO is proven shorthand for Bo Staff. Kamadan often puts 20/20
        // between the shorthand and the family word:
        //   BO 20/20 Staff -> Bo Staff 20/20
        $message = preg_replace(
            '/\bBO\s+(\d{1,2}\/\d{1,2})\s+Staff\b/iu',
            'Bo Staff $1',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8F4_DEFENDER_CANONICAL
        // The concrete GW weapon is catalogued as "Defender".
        // Traders commonly write "Defender Shield":
        //   q9 Defender Shield 400gv -> q9 Defender 400gv
        $message = preg_replace(
            '/\bDefender\s+Shield\b/iu',
            'Defender',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8G_ETERNAL_BOW_TYPO
        // Proven Kamadan typo:
        //   q10 eternl bow -> q10 Eternal Bow
        $message = preg_replace(
            '/\beternl\s+bow\b/iu',
            'Eternal Bow',
            $message
        ) ?? $message;

        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = str_replace(["\r\n", "\r", "\t", '_'], [' ', ' ', ' ', ' '], $message);
        // Normalize verbose trade intent before OfferSplitter looks for markers.
        $message = preg_replace('/\bwant\s+to\s+buy\b/iu', 'WTB', $message) ?? $message;
        $message = preg_replace('/\bwant\s+to\s+sell\b/iu', 'WTS', $message) ?? $message;

        // LITTYWATCH_PHASE8B3_TRADE_DIRECTION_RECOVERY
        // Recover unambiguous decorated/spaced Kamadan trade markers before
        // OfferSplitter searches for canonical WTB/WTS/WTT tokens.
        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*s(?![a-z0-9])/iu',
            'WTS',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*b(?![a-z0-9])/iu',
            'WTB',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*t(?!\s*-\s*b\b)(?![a-z0-9])/iu',
            'WTT',
            $message
        ) ?? $message;

        // Occasional single junk character directly before an otherwise
        // canonical WTS marker in collected Kamadan text.
        $message = preg_replace(
            '/(?<![a-z0-9])[wn]WTS\b/iu',
            'WTS',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8C1_SELL_SHORTHAND
        // GW trade shorthand: "S:" at the start means selling.
        $message = preg_replace(
            '/^\s*S\s*:\s*/iu',
            'WTS ',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8C2_PROVEN_WTS_TYPOS
        // Historical message pairs show TS/WRS immediately followed by the
        // same advertisement written as WTS. Restrict recovery to weapon-style
        // requirement syntax to avoid treating arbitrary TS/WRS text as sell.
        $message = preg_replace(
            '/^\s*(?:TS|WRS)\s+(?=(?:q|r|req)\s*[0-9]{1,2}\b)/iu',
            'WTS ',
            $message
        ) ?? $message;
        $message = preg_replace('/\^{2,}/u', ' | ', $message) ?? $message;
        $message = preg_replace('/(?<=\D)\^(?=\D)/u', ' ', $message) ?? $message;
        // Normalize spaced requirement notation before segmentation: "q 9" -> "q9".
        $message = preg_replace('/\b([qr])\s+([0-9]{1,2})\b/iu', '$1$2', $message) ?? $message;

        // Decorative Kamadan punctuation is presentation, not market data.
        // Keep meaningful single '-' stat markers intact, but collapse long runs.
        $message = preg_replace('/(?<!\d)[!?.*_=]{3,}/u', ' ', $message) ?? $message;
        $message = preg_replace('/-{3,}/u', ' ', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        return trim($message, " \t\n\r\0\x0B|,;:!?.=_-");
    }
}
