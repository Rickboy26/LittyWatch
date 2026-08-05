<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class Tokenizer
{
    /** @return list<array{value:string,normalized:string,start:int,length:int}> */
    public function tokenize(string $text): array
    {
        preg_match_all('/(?:\d+(?:[.,]\d+)?(?:\^\d+)?|[\p{L}]+(?:[\'’\-][\p{L}]+)*|[^\s])/u', $text, $matches, PREG_OFFSET_CAPTURE);
        $tokens = [];
        foreach ($matches[0] as [$value, $start]) {
            $tokens[] = [
                'value' => $value,
                'normalized' => mb_strtolower($value),
                'start' => $start,
                'length' => strlen($value),
            ];
        }
        return $tokens;
    }
}
