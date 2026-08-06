<?php
declare(strict_types=1);
namespace LittyWatch\Parser;

final class MessageClassifier
{
    public function __construct(private ?DynamicKnowledge $knowledge = null) {}
    /** @return array{kind:string,reason:string} */
    public function classify(string $text): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($clean === '') return ['kind'=>'noise','reason'=>'empty'];
        $learned=$this->knowledge?->exclusion($clean);if($learned!==null)return$learned;

        if (preg_match('/^(?:pm(?:\s+me)?|wsp(?:\s+me)?|open\s+trade|trade\s+me|message\s+me|whisper\s+me|offers?|show\s+me|ty|thanks?)[.! ]*$/iu', $clean)) {
            return ['kind'=>'noise','reason'=>'contact_instruction'];
        }
        if (preg_match('/\b(?:names?|character\s+names?|ign\s+names?)\s*:/iu', $clean)) {
            return ['kind'=>'character_name_sale','reason'=>'character_name_sale'];
        }

        $serviceWords = ['mission','fow armor','any quest','vanquish','dungeon','furnace','duncan','mallyx','deep','urgoz','runner','running','rush','powerlevel','service'];
        $hits = 0;
        $lower = mb_strtolower($clean);
        foreach ($serviceWords as $word) {
            if (str_contains($lower, $word)) $hits++;
        }
        if ($hits >= 2 || preg_match('/\b(?:mission|quest|vanquish|dungeon|run|rush|service)s?\b.*\b\d+(?:[.,]\d+)?a\b/iu', $clean)) {
            return ['kind'=>'service','reason'=>'service_advertisement'];
        }
        return ['kind'=>'market','reason'=>'market_candidate'];
    }
}
