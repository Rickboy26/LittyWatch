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

        if (preg_match('/^\s*pc(?:\s+on|\s*:|\s+)/iu', $clean)) {
            return ['accepted'=>false,'kind'=>'price_check','reason'=>'price_check'];
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

        if (preg_match('/\b(?:show me what you have|anything cool|open trade|give me my storage spaces back)\b/iu', $clean)) {
            return ['accepted'=>false,'kind'=>'noise','reason'=>'non_specific_request'];
        }

        return ['accepted'=>true,'kind'=>'market','reason'=>'market_candidate'];
    }
}
