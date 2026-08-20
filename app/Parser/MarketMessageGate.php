<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Rejects messages that are not item-market advertisements before
 * segmentation can turn words such as colors or locations into fake items.
 */
final class MarketMessageGate
{
    /** @return array{accepted:bool,kind:string,reason:string} */
    public function inspect(string $message): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        $lower = strtolower($clean);

        if ($clean === '') {
            return ['accepted'=>false,'kind'=>'noise','reason'=>'empty'];
        }

        if (preg_match('/^(?:hello|hi|hey|test|ty|thanks?|lol|xd)[.! ]*$/iu', $clean)) {
            return ['accepted'=>false,'kind'=>'noise','reason'=>'conversation'];
        }

        // Phase 8B.1: pure price-check / valuation requests are not market offers.
        // Keep mixed messages with an explicit WTB/WTS/WTT intent, so a valid
        // advertisement is not discarded merely because it also mentions a PC.
        $hasExplicitTradeIntent = preg_match(
            '/\b(?:wtb|wts|wtt|buying|selling|trading)\b/iu',
            $clean
        ) === 1;

        $hasPriceCheckIntent = (
            preg_match('/^\s*pc(?:\s+on|\s+for|\s*:|\s+)/iu', $clean) === 1
            || preg_match('/\bprice\s*check\b/iu', $clean) === 1
            || preg_match('/\bpricecheck\b/iu', $clean) === 1
        );

        if ($hasPriceCheckIntent && !$hasExplicitTradeIntent) {
            return ['accepted'=>false,'kind'=>'price_check','reason'=>'price_check'];
        }

        // Phase 8B.2a: clear non-offer intents.
        // These are conversations, valuations, completed transactions,
        // giveaways or service requests rather than current market offers.
        // Never reject the complete message here when it also contains an
        // explicit WTB/WTS/WTT marker.
        if (!$hasExplicitTradeIntent) {
            if (
                preg_match('/\b(?:what(?:\s+is|\'s)\s+the\s+price|what\s+are\s+the\s+prices?|how\s+much\s+(?:is|are))\b/iu', $clean)
            ) {
                return ['accepted'=>false,'kind'=>'price_question','reason'=>'price_question'];
            }

            if (
                preg_match('/\b(?:just\s+bought|i\s+(?:just\s+)?bought|bought\s+(?:a|an)\b)\b/iu', $clean)
            ) {
                return ['accepted'=>false,'kind'=>'historical_trade','reason'=>'historical_purchase'];
            }

            if (
                preg_match('/\b(?:giving\s+(?:out|away)|giveaway|for\s+free|free\s+(?:(?:monk|rit(?:ualist)?|warrior|ranger|necro(?:mancer)?|mesmer|elementalist|assassin|paragon|dervish)(?:\s*(?:and|\/|,)\s*(?:monk|rit(?:ualist)?|warrior|ranger|necro(?:mancer)?|mesmer|elementalist|assassin|paragon|dervish))?\s+)?(?:tomes?|runes?|items?))\b/iu', $clean)
            ) {
                return ['accepted'=>false,'kind'=>'giveaway','reason'=>'giveaway'];
            }

            if (
                preg_match('/\b(?:lf|looking\s+for|need(?:ing)?)\s+(?:a\s+)?runner\b/iu', $clean)
            ) {
                return ['accepted'=>false,'kind'=>'service','reason'=>'runner_request'];
            }

            if (
                preg_match('/\bhow\s+many\b.{0,80}\b(?:do\s+u|do\s+you)\s+have\b/iu', $clean)
                || preg_match('/\b(?:do\s+u|do\s+you)\s+still\s+have\b/iu', $clean)
                || preg_match('/\btake\s+a\s+look\s+if\b/iu', $clean)
                || preg_match('/\b(?:around\s+i\s+would\s+say|are\s+possible\s+too)\b/iu', $clean)
                || preg_match('/\bnewly\s+gearing\s+this\s+character\b/iu', $clean)
                || preg_match('/\bim\s+far\s+away\s+from\b/iu', $clean)
                || preg_match('/\bqqun\s+aurait\s+encore\b/iu', $clean)
            ) {
                return ['accepted'=>false,'kind'=>'conversation','reason'=>'trade_conversation'];
            }
        }

        $guildPatterns = [
            'trim your guild cape',
            'guild cape',
            'is recruiting',
            'guild is recruiting',
            'recruiting for',
            'join our guild',
            'join the guild',
            'active alliance',
            'alliance recruiting',
            'looking for a guild',
            'lf guild',
            'guild hall',
        ];
        foreach ($guildPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return ['accepted'=>false,'kind'=>'guild_advertisement','reason'=>'guild_advertisement'];
            }
        }

        $servicePatterns = [
            'missions:',
            'mission:',
            'factions rush',
            'campaign rush',
            'fow armor',
            'any quest',
            'vanquish',
            'dungeon run',
            'running service',
            'powerlevel',
            'power level',
        ];
        foreach ($servicePatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return ['accepted'=>false,'kind'=>'service','reason'=>'service_advertisement'];
            }
        }

        // Phase 3V: classify transport/running/tour services before item parsing.
        // Keep this contextual so the inscription "Run for Your Life" is not lost.
        if (
            preg_match('/\bservices?\s*:/iu', $clean)
            || preg_match('/\b(?:outpost|mission|campaign|crystal\s+desert|prophecies|factions?|nightfall|eotn|eye\s+of\s+the\s+north)\b[^|;]{0,80}\b(?:run|runs|tour|tours|rush|rushing)\b/iu', $clean)
            || preg_match('/\b(?:run|runs|tour|tours|rush|rushing)\b[^|;]{0,80}\b(?:outpost|mission|campaign|kodash|lions?\s+arch|ascalon|docks?|factions?|nightfall|prophecies|eotn)\b/iu', $clean)
            || preg_match('/\b(?:ferry|taxi)\b[^|;]{0,50}\b(?:to|from|docks?|outpost)\b/iu', $clean)
        ) {
            return ['accepted'=>false,'kind'=>'service','reason'=>'service_transport_or_tour'];
        }

        if (preg_match('/\b(?:show me what you have|anything cool|open trade|give me my storage spaces back)\b/iu', $clean)) {
            return ['accepted'=>false,'kind'=>'noise','reason'=>'non_specific_request'];
        }

        return ['accepted'=>true,'kind'=>'market','reason'=>'market_candidate'];
    }
}
