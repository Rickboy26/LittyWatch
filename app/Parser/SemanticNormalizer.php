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
        $text = preg_replace('/\b(?:insc|inscr|inscrib|inscribable|inscriptable)\b/iu', 'inscribable', $text) ?? $text;

        // Compact currency/item notation from Kamadan: ZKey1.3e -> ZKey 1.3e.
        $text = preg_replace('/\b(zkey|szc)(?=\d)/iu', '$1 ', $text) ?? $text;
        // Defensive cleanup for learned aliases that accidentally duplicated a family label.
        $text = preg_replace('/\b(Deld(?:rimor|imore)?\s+Hero\s+armor)\s+Hero\s+armor\b/iu', '$1', $text) ?? $text;

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
            // Phase 2E: high-confidence residual aliases from Parser Review.
            '/\b(?:all\s+)?tot\s+bags?\b/iu' => 'Trick-or-Treat Bag',
            '/\btrick\s*(?:-|\s)?or\s*(?:-|\s)?treat\s+bags?\b/iu' => 'Trick-or-Treat Bag',
            '/\bclockwork\s+scy\b/iu' => 'Clockwork Scythe',
            '/\bdeld(?:rimor|imore)?\s+(?:hero\s+)?armor(?!\s+remnants?\b)/iu' => 'Deldrimor Armor Remnant',
            '/\bcloth\s+(?:hero\s+)?armor\b/iu' => 'Cloth of Brotherhood',
            '/\bmysterious\s+(?:hero\s+)?armor(?!\s+pieces?\b)/iu' => 'Mysterious Armor Piece',
            '/\bprimeval\s+(?:hero\s+)?armor(?!\s+remnants?\b)/iu' => 'Primeval Armor Remnant',
            '/\bsunspear\s+(?:hero\s+)?armor\b/iu' => 'Stolen Sunspear Armor',
            '/\beshortbow\b/iu' => 'Eternal Bow',
        ];
        foreach ($marketAliases as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // Phase 2F: canonical names must remain idempotent after repeated normalization.
        $text = preg_replace('/\bDeldrimor Armor Remnant(?:\s+Remnant|\s+remnants?)+\b/iu', 'Deldrimor Armor Remnant', $text) ?? $text;
        $text = preg_replace('/\bMysterious Armor Piece(?:\s+Piece|\s+pieces?)+\b/iu', 'Mysterious Armor Piece', $text) ?? $text;
        $text = preg_replace('/\bPrimeval Armor Remnant(?:\s+Remnant|\s+remnants?)+\b/iu', 'Primeval Armor Remnant', $text) ?? $text;

        // Phase 2P: final live-queue cleanup. Expand the compact spear-mod
        // advertisement into actual GW1 upgrade component names. This keeps the
        // modifier words from being learned as standalone items.
        $text = preg_replace('/\bspear\s+def\s*\/\s*ench\s*\/\s*cruel\s*\/\s*shock(?:ing)?\b/iu',
            'Spear Grip of Defense | Spear Grip of Enchanting | Cruel Spearhead | Shocking Spearhead', $text) ?? $text;
        // Exact observed typo: the only GW1 item named "Large Equipment ..." is
        // Large Equipment Pack. Keep this intentionally narrow.
        $text = preg_replace('/\blarge\s+equipment\s+staff\b/iu', 'Large Equipment Pack', $text) ?? $text;

        // Phase 2O: final low-confidence component/name normalization.
        $text = preg_replace('/["“”]?\bof\s+the\s+ritualist["“”]?\s+wand\s+wra(?:p(?:ping)?)?\b/iu', 'Wand Wrapping of the Ritualist', $text) ?? $text;
        $text = preg_replace('/\britualist\s+wand\s+wra(?:p(?:ping)?)?\b/iu', 'Wand Wrapping of the Ritualist', $text) ?? $text;
        $text = preg_replace('/\baptitude\s+focus\s+core\b/iu', 'Focus Core of Aptitude', $text) ?? $text;
        $text = preg_replace('/\bswift\s+for\s+focus\b/iu', 'Focus Core of Swiftness', $text) ?? $text;
        $text = preg_replace('/\beternal\s+flat\s+bow\b/iu', 'Eternal Bow', $text) ?? $text;

        // Phase 2M: concrete consumable/trophy shorthand from the residual queue.
        $text = preg_replace('/\bturtle\s+stones?\b/iu', 'Jadeite Summoning Stone', $text) ?? $text;
        $text = preg_replace('/\bgolden\s+eggs?\b/iu', 'Golden Egg', $text) ?? $text;
        $text = preg_replace('/\bhero\s*box(?:es)?\b/iu', "Hero's Strongbox", $text) ?? $text;
        $text = preg_replace('/\bherobox(?:es)?\b/iu', "Hero's Strongbox", $text) ?? $text;
        $text = preg_replace('/\bchar\s+carvings?\b/iu', 'Charr Carving', $text) ?? $text;
        // Bare Rin/Diessa are standard stack shorthand when they are sold as list entries.
        $text = preg_replace('/(?<![\p{L}])Rin(?=\s+(?:\d+(?:[.,]\d+)?[eakg]|$))/iu', 'Rin Relic', $text) ?? $text;
        $text = preg_replace('/(?<![\p{L}])Diessa(?=\s+(?:\d+(?:[.,]\d+)?[eakg]|$))/iu', 'Diessa Chalice', $text) ?? $text;

        // Phase 2L: final concrete skin aliases and truncated review spellings.
        $text = preg_replace('/\bPrenerf\s+Strongroot(?:\'s)?\s+Shelte\b/iu', "Strongroot's Shelter", $text) ?? $text;
        $text = preg_replace('/\bStrongroot(?:\'s)?\s+Shelte\b/iu', "Strongroot's Shelter", $text) ?? $text;
        $text = preg_replace('/\bapt\s+not\s+att(?:\s+\d+%)?/iu', 'Aptitude Not Attitude', $text) ?? $text;
        $text = preg_replace('/\bAttitude\s+not\s+Aptitude\s+not\s+att(?:\s+\d+%)?/iu', 'Aptitude Not Attitude', $text) ?? $text;
        $text = preg_replace('/\bPlagueb\s+D\b/iu', 'Plagueborn Daggers', $text) ?? $text;
        $text = preg_replace('/\bChromium\s+Shard\b/iu', 'Chromium Shards', $text) ?? $text;

        // Phase 2K: final residual shorthand and malformed-list cleanup.
        $text = preg_replace('/\baptitude\s+no\s+attitude\b/iu', 'Aptitude not Attitude', $text) ?? $text;
        $text = preg_replace('/\bBUs?\b/u', 'Essence of Celerity', $text) ?? $text;
        $text = preg_replace('/\bEL\s+Avatar\s+of\s+Balthazar\b/iu', 'Everlasting Avatar of Balthazar Tonic', $text) ?? $text;
        $text = preg_replace('/\bCele\s+Horse\b/iu', 'Miniature Celestial Horse', $text) ?? $text;
        $text = preg_replace('/\bClovers?\b/iu', 'Four-Leaf Clover', $text) ?? $text;
        $text = preg_replace('/\bDeldrimor\s+Ancient\s+Sunspear\b/iu', 'Deldrimor Armor Remnant | Ancient Armor Remnant | Stolen Sunspear Armor', $text) ?? $text;
        $text = preg_replace('/\bEstorage\b/iu', 'Energy Storage', $text) ?? $text;
        $text = preg_replace('/\bFoc\b/iu', 'Focus', $text) ?? $text;
        // Commas after a priced OS weapon start the next offer; keep the preceding
        // 15_en / 15_stanc modifier attached to its own weapon instead of the next skin.
        $text = preg_replace('/(\d+(?:[.,]\d+)?\s*(?:e|a|k|g))\s*,\s*(?=OS\b)/iu', '$1 | ', $text) ?? $text;

        // Phase 2I: high-confidence residual catalog spellings from the live review queue.
        $text = preg_replace('/\bgolden\s+zaishen\s+coins?\b/iu', 'Gold Zaishen Coin', $text) ?? $text;
        $text = preg_replace('/\bflames?\s+of\s+balthazar\b/iu', 'Flames of Balthazar', $text) ?? $text;
        $text = preg_replace('/\bsup(?:erior)?\s+rune\s+(?:of\s+)?vigor\b/iu', 'Superior Rune of Vigor', $text) ?? $text;
        $text = preg_replace('/\bstygian\s+gems?\b/iu', 'Stygian Gem', $text) ?? $text;
        $text = preg_replace('/\bstack\s+of\s+iron\b/iu', 'Iron Ingot stack', $text) ?? $text;

        // Phase 2H: residual GW1 market shorthand that is safe to canonicalize.
        $text = preg_replace('/\bbirds?[ -]?eye\b/iu', 'Birdseye', $text) ?? $text;
        $text = preg_replace('/\bechovald(?=\s+(?:q|r|req|tac|tactics|str|strength|\d))/iu', 'Echovald Shield', $text) ?? $text;
        $text = preg_replace('/\bhog(?:\'s)?\s+glut\b/iu', "Hog's Gluttony", $text) ?? $text;
        $text = preg_replace('/\bmap\s+(?:piece\s+)?sets?\b/iu', 'Map Set', $text) ?? $text;
        $text = preg_replace('/\bfull\s+map\s+set\b/iu', 'Map Set', $text) ?? $text;
        $text = preg_replace('/\bdiessa\s+sets?\b/iu', 'Diessa Set', $text) ?? $text;

        // Phase 2G: recurring market shorthand / typo normalization.
        $text = preg_replace('/\bstrenght\s*&\s*honor\b/iu', 'Strength and Honor', $text) ?? $text;
        $text = preg_replace('/\bstrenght\s+and\s+honor\b/iu', 'Strength and Honor', $text) ?? $text;
        $text = preg_replace('/\bmaster\s+fo\s+my\s+domain\b/iu', 'Master of My Domain', $text) ?? $text;
        $text = preg_replace('/\bapt\s+not\s+att\b/iu', 'Aptitude not Attitude', $text) ?? $text;
        $text = preg_replace('/\b(?:gold\s+zcoins?|gzc)\b/iu', 'Gold Zaishen Coin', $text) ?? $text;
        $text = preg_replace('/\b(?:silver\s+zcoins?|szc)\b/iu', 'Silver Zaishen Coin', $text) ?? $text;
        $text = preg_replace('/\bhero(?:s|\'s)?\s+strong\s*boxes?\b/iu', "Hero's Strongbox", $text) ?? $text;
        $text = preg_replace('/\bprimeval\s+remnants?\b/iu', 'Primeval Armor Remnant', $text) ?? $text;
        $text = preg_replace('/\bforest\s+griff(?:on|in|en)\b/iu', 'Miniature Forest Griffon', $text) ?? $text;
        $text = preg_replace('/\bwaili(?:ng\s+lord)?\b/iu', 'Miniature Wailing Lord', $text) ?? $text;
        $text = preg_replace('/\bmadr(?:uk)?\b(?!\s+dhuum)/iu', 'Miniature Madruk Dhuum', $text) ?? $text;
        $text = preg_replace('/\bd\.?\s*cake\b/iu', 'Birthday Cupcake', $text) ?? $text;
        $text = preg_replace('/\bice\s*tea\b/iu', 'Battle Isle Iced Tea', $text) ?? $text;
        // Common three-item consumable request.
        $text = preg_replace('/\bbeacon\s*\/\s*tea\s*\/\s*cake\b/iu',
            'Party Beacon | Battle Isle Iced Tea | Birthday Cupcake', $text) ?? $text;

        $text = preg_replace('/\bred\s+rocks?\b/iu', 'Red Rock Candy', $text) ?? $text;
        $text = preg_replace('/\brainbows?\b/iu', 'Rainbow Candy Cane', $text) ?? $text;
        $text = preg_replace('/\bm4m\b/iu', 'Measure for Measure', $text) ?? $text;
        $text = preg_replace('/\bcrested\s*machette\b/iu', 'Crested Machete', $text) ?? $text;
        $text = preg_replace('/\bnick\s+sete\b/iu', 'Nicholas Set', $text) ?? $text;
        $text = preg_replace('/\bperf(?:ect)?\s+salvage\s+kits?\b/iu', 'Perfect Salvage Kit', $text) ?? $text;
        $text = preg_replace('/\bblessings\s+of\s+war\b/iu', 'Blessing of War', $text) ?? $text;
        $text = preg_replace('/\b(?:hero(?:s|\'s)?\s+strong\s*box|hero\s+boxes?)\b/iu', "Hero's Strongbox", $text) ?? $text;

        // Phase 2T: fresh Kamadan generalization. Normalize newly observed
        // 20th-anniversary skins, materials, strongbox shorthand and common typos
        // before grammar segmentation creates fallback item names.
        $text = preg_replace('/\bCC(?=\s+(?:Q|R)\s*\d{1,2}\b)/iu', 'Celestial Compass', $text) ?? $text;
        $text = preg_replace('/\bHourglass(?=\s+(?:Q|R)\s*\d{1,2}\b)/iu', 'Hourglass Staff', $text) ?? $text;
        $text = preg_replace('/\bPeacocks\s+Wrath\b/iu', "Peacock's Wrath", $text) ?? $text;
        $text = preg_replace('/\bMysterious\s+Summonig\s+Stones?\b/iu', 'Mysterious Summoning Stone', $text) ?? $text;
        $text = preg_replace('/\bStars?\s+of\s+Transf(?:erence)?\b/iu', 'Star of Transference', $text) ?? $text;
        $text = preg_replace('/\b\d+\s*Elonian\s+Leather\s+Squares?\b/iu', 'Elonian Leather Square', $text) ?? $text;
        // Preserve miniature dedication as metadata-bearing suffix. Prefix qualifiers
        // can be consumed by later candidate/segment cleanup, so canonicalize
        // `unded Gpriest` / `ded Ghostly Priest` to `<item> unded|ded`.
        $text = preg_replace_callback(
            '/\b(unded(?:icated)?|ded(?:icated)?)\s+(?:g\s*priest|ghostly\s+priest)\b/iu',
            static fn(array $m): string => 'Miniature Ghostly Priest ' . (str_starts_with(mb_strtolower($m[1]), 'unded') ? 'unded' : 'ded'),
            $text
        ) ?? $text;
        $text = preg_replace('/\bghostly\s+priest\b/iu', 'Miniature Ghostly Priest', $text) ?? $text;
        // In Kamadan these bare labels with armbrace prices refer to the PvP boxes,
        // not the title/NPC concepts. Keep the rewrite price-contextual and narrow.
        $text = preg_replace('/\bSTRATEGIST\b(?=\s+\d+(?:[.,]\d+)?\s*A\b)/u', "Strategist's Zaishen Strongbox", $text) ?? $text;
        $text = preg_replace('/\bHERO\b(?=\s+\d+(?:[.,]\d+)?\s*A\b)/u', "Hero's Strongbox", $text) ?? $text;

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

        // Phase 3V: canonical miniature identities are applied before the
        // generic fallback path. Dedication remains metadata, not part of the name.
        $text = preg_replace_callback(
            '/\b(unded(?:icated)?|ded(?:icated)?)\s+ghostly\s+hero\b/iu',
            static fn(array $m): string => 'Miniature Ghostly Hero ' . (str_starts_with(mb_strtolower($m[1]), 'unded') ? 'unded' : 'ded'),
            $text
        ) ?? $text;
        $text = preg_replace('/\bmini(?:ature)?\s+undead\s+prince(?:\s+rurik)?\b/iu', 'Miniature Undead Prince Rurik', $text) ?? $text;
        $text = preg_replace('/\bundead\s+prince\s+rurik\b/iu', 'Miniature Undead Prince Rurik', $text) ?? $text;
        $text = preg_replace('/\belixirs?\s+of\s+valor\b/iu', 'Elixir of Valor', $text) ?? $text;

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
            '/\bg\s*priest\b/iu'=>'Miniature Ghostly Priest',
        ];
        foreach ($rules as $pattern=>$replacement) $text = preg_replace($pattern,$replacement,$text) ?? $text;
        return trim($text, " \t\n\r\0\x0B|;,");
    }
}
