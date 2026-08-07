<?php
declare(strict_types=1);
namespace LittyWatch\Parser;

final class SemanticNormalizer
{
    public function __construct(private ?DynamicKnowledge $knowledge = null) {}
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
        $text=$this->knowledge?->aliases($text)??$text;
        $text = preg_replace('/\bpm(?:\s+me)?\b.*$/iu','',$text) ?? $text;
        $text = preg_replace('/\bopen\s+trade\b.*$/iu','',$text) ?? $text;

        $text = preg_replace('/\b([qr])\s+([0-9]{1,2})\b/iu', '$1$2', $text) ?? $text;
        $text = preg_replace('/\brq\s*([0-9]{1,2})\b/iu', 'q$1', $text) ?? $text;
        $text = preg_replace('/\breq(?:uirement)?\s*([0-9]{1,2})\b/iu', 'q$1', $text) ?? $text;
        $text = preg_replace('/\binsc(?:r(?:ibable|iptable)?)?\b/iu', 'inscribable', $text) ?? $text;

        // Compact market notation frequently glues requirement + item together.
        $text = preg_replace('/\b([qr])\s*([0-9]{1,2})(?=[A-Za-z])/iu', '$1$2 ', $text) ?? $text;

        // High-frequency community aliases from the remaining review queue.
        $marketAliases = [
            '/\bobby\s+shards?\b/iu' => 'Obsidian Shard',
            '/\b(?:drok|droknar)\s+keys?\b/iu' => "Droknar's Key",
            '/\bres(?:urrection)?\s+scrolls?\b/iu' => 'Scroll of Resurrection',
            '/\bstack\s+of\s+res(?:urrection)?\s+scrolls?\b/iu' => 'Scroll of Resurrection stack',
            '/\b(?:candy\s+)?apples?\b/iu' => 'Candy Apple',
            '/\b(?:pumpkin\s+)?pies?\b/iu' => 'Slice of Pumpkin Pie',
            '/\bcookies?\b/iu' => 'Pumpkin Cookie',
            '/\b(?:candy\s+)?corn\b/iu' => 'Candy Corn',
            '/\b(?:kebab|kabob)s?\b/iu' => 'Drake Kabob',
            '/\blunars?\b/iu' => 'Lunar Fortune',
            '/\blightbringer\s+scrolls?\b/iu' => 'Scroll of the Lightbringer',
            '/\brez\b/iu' => 'Scroll of Resurrection',
            '/\bz\s*keys?\b|\bzkeys?\b/iu' => 'Zaishen Key',
            '/\bparty\s+beaco(?:n)?\b/iu' => 'Party Beacon',
            '/\bsephis\s+word\b/iu' => 'Sephis Sword',
            '/\bfrogg?y?\b/iu' => 'Frog Scepter',
        ];
        foreach ($marketAliases as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // Birthday items are commonly advertised as ranges/shorthand in Kamadan.
        $birthdayRange = implode(' | ', [
            'Xunlai Birthday Present 1st Year', 'Xunlai Birthday Present 2nd Year',
            'Xunlai Birthday Present 3rd Year', 'Xunlai Birthday Present 4th Year',
            'Xunlai Birthday Present 5th Year', 'Xunlai Birthday Present 6th Year',
            'Xunlai Birthday Present 7th Year',
        ]);
        $text = preg_replace('/\b(?:bday|birthday)\s+presents?\s*1\s*(?:-|to|through)\s*7\b/iu', $birthdayRange, $text) ?? $text;
        $text = preg_replace('/\b(?:xunlai\s+)?birthday\s+present\s+vouchers?\b/iu', 'Xunlai Birthday Voucher', $text) ?? $text;
        $text = preg_replace('/\bbday\s+present\s+vouchers?\b/iu', 'Xunlai Birthday Voucher', $text) ?? $text;

        // Collapse alias + canonical name combinations to one catalog item.
        $text = preg_replace('/\bvolta\s*\(\s*voltaic\s+spear\s*\)/iu', 'Voltaic Spear', $text) ?? $text;
        $text = preg_replace('/\bg\s*priest\s*\(\s*miniature\s+ghostly\s+priest\s*\)/iu', 'Miniature Ghostly Priest', $text) ?? $text;

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
            '/\bvolta\b/iu'=>'Voltaic Spear',
            '/\bg\s*priest\b/iu'=>'Miniature Ghostly Priest',
        ];
        foreach ($rules as $pattern=>$replacement) $text = preg_replace($pattern,$replacement,$text) ?? $text;
        return trim($text, " \t\n\r\0\x0B|;,");
    }
}
