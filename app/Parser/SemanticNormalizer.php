<?php
declare(strict_types=1);
namespace LittyWatch\Parser;

final class SemanticNormalizer
{
    private const PROFESSIONS = [
        'war'=>'Warrior','warr'=>'Warrior','warrior'=>'Warrior',
        'rang'=>'Ranger','ranger'=>'Ranger','mo'=>'Monk','monk'=>'Monk',
        'necro'=>'Necromancer','necromancer'=>'Necromancer',
        'mes'=>'Mesmer','mesmer'=>'Mesmer','ele'=>'Elementalist','elementalist'=>'Elementalist',
        'sin'=>'Assassin','assassin'=>'Assassin','rit'=>'Ritualist','ritualist'=>'Ritualist',
        'para'=>'Paragon','paragon'=>'Paragon','derv'=>'Dervish','dervish'=>'Dervish',
    ];

    public function normalize(string $text): string
    {
        $text = trim(preg_replace('/\s+/u',' ', $text) ?? $text);
        $text = preg_replace('/\bpm(?:\s+me)?\b.*$/iu','',$text) ?? $text;
        $text = preg_replace('/\bopen\s+trade\b.*$/iu','',$text) ?? $text;

        $text = preg_replace_callback(
            '/\b(elite\s+)?(war|warr|warrior|rang|ranger|mo|monk|necro|necromancer|mes|mesmer|ele|elementalist|sin|assassin|rit|ritualist|para|paragon|derv|dervish)\s+tomes?\b/iu',
            static fn(array $m): string => (!empty($m[1])?'Elite ':'') . (self::PROFESSIONS[mb_strtolower($m[2])] ?? $m[2]) . ' Tome',
            $text
        ) ?? $text;

        $rules = [
            '/\bunded(?:icated)?\s+dhuum\b/iu'=>'Miniature Dhuum unded',
            '/\bded(?:icated)?\s+dhuum\b/iu'=>'Miniature Dhuum ded',
            '/\bunded(?:icated)?\s+destroyer(?:\s+of\s+flesh)?\b/iu'=>'Miniature Destroyer of Flesh unded',
            '/\bded(?:icated)?\s+destroyer(?:\s+of\s+flesh)?\b/iu'=>'Miniature Destroyer of Flesh ded',
            '/\bel\s+destroyer(?:\s+tonic)?\b/iu'=>'Everlasting Destroyer Tonic',
            '/\bel\s+([A-Za-z][A-Za-z\'’ -]+?)\s+tonic\b/iu'=>'Everlasting $1 Tonic',
            '/\btengu\s+flares?\b/iu'=>'Tengu Support Flare',
            '/\btengu\b(?!\s+(?:support\s+)?flare)/iu'=>'Tengu Support Flare',
            '/\bghastly\b(?!\s+summoning\s+stone)/iu'=>'Ghastly Summoning Stone',
            '/\bwar\s+suppl(?:y|ies)\b/iu'=>'War Supplies',
            '/\brin\s+relics?\s+set\b/iu'=>'Rin Relic set',
        ];
        foreach ($rules as $pattern=>$replacement) $text = preg_replace($pattern,$replacement,$text) ?? $text;
        return trim($text, " \t\n\r\0\x0B|;,");
    }
}
