<?php
declare(strict_types=1);
namespace LittyWatch\Market;

/**
 * Phase 3U: market-facing identity is stricter than parser/wiki labels.
 * These are legacy LittyWatch identities whose canonical in-game names are known.
 */
final class CanonicalMarketIdentity
{
    /** @var array<string,string> */
    private const BY_KEY = [
        'silverwing-bow' => 'Silverwing Recurve Bow',
        'ghostly-hero' => 'Miniature Ghostly Hero',
        'rift-warden' => 'Miniature Rift Warden',
        'mallyx' => 'Miniature Mallyx',
        'kuuna' => 'Miniature Kuunavang',
        'mad-kings-guard' => "Miniature Mad King's Guard",
        // LITTYWATCH_PHASE4D1_UNDEAD_PRINCE
        'mini-undead-prince-rurik' => 'Miniature Undead Prince',
    ];

    /** @var array<string,string> */
    private const LEGACY_NAMES = [
        'silverwing bow' => 'Silverwing Recurve Bow',
        'silverwing' => 'Silverwing Recurve Bow',
        'ghostly hero' => 'Miniature Ghostly Hero',
        'rift warden' => 'Miniature Rift Warden',
        'mallyx' => 'Miniature Mallyx',
        'kuuna' => 'Miniature Kuunavang',
        'kuunavang' => 'Miniature Kuunavang',
        "mad king's guard" => "Miniature Mad King's Guard",
        'mad kings guard' => "Miniature Mad King's Guard",
        'miniature undead prince rurik' => 'Miniature Undead Prince',
        'undead prince rurik' => 'Miniature Undead Prince',
    ];

    public static function nameFor(string $name,string $key=''): string
    {
        $key=trim($key);
        if($key!=='' && isset(self::BY_KEY[$key])) return self::BY_KEY[$key];
        $n=self::normalize($name);
        return self::LEGACY_NAMES[$n] ?? trim($name);
    }

    public static function isWikiDisambiguator(string $name): bool
    {
        return preg_match('/\s+\((?:weapon|item|object|disambiguation)\)$/iu',trim($name))===1;
    }

    /** @return array<string,string> */
    public static function overrides(): array { return self::BY_KEY; }

    private static function normalize(string $value): string
    {
        $value=mb_strtolower(trim(str_replace('’',"'",$value)));
        return trim(preg_replace('/\s+/u',' ',$value)??$value);
    }
}
