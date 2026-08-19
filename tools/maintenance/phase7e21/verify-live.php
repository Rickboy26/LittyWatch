<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.21 Accepted Safety verification ===".PHP_EOL;

$checks = [
    'Scepter -> Staff false accepted' => "
        lower(COALESCE(raw_segment,'')) LIKE '%scepter%'
        AND lower(COALESCE(item,'')) LIKE '%staff%'
        AND quality_status='accepted'
    ",
    '15% ench -> of Enchanting false accepted' => "
        item_key='of-enchanting'
        AND lower(COALESCE(raw_segment,'')) LIKE '%15%ench%'
        AND quality_status='accepted'
    ",
    'Bow -> Blessing of War false accepted' => "
        item_key='blessing-of-war'
        AND lower(COALESCE(raw_segment,'')) LIKE '%bow%'
        AND quality_status='accepted'
    ",
    'Rune <- Shield false accepted' => "
        lower(COALESCE(item_key,'')) LIKE '%rune%'
        AND lower(COALESCE(raw_segment,'')) LIKE '%shie%'
        AND quality_status='accepted'
    ",
    'Wand -> Chakram false accepted' => "
        lower(COALESCE(raw_segment,'')) LIKE '%wand%'
        AND lower(COALESCE(item_key,'')) LIKE '%chakram%'
        AND quality_status='accepted'
    ",
    'Impossible shield attribute accepted' => "
        lower(COALESCE(item_key,'')) LIKE '%shield%'
        AND lower(COALESCE(attribute_key,'')) IN (
            'communing','channeling_magic','restoration_magic','spawning_power',
            'domination_magic','illusion_magic','fast_casting','inspiration_magic',
            'death_magic','curses','soul_reaping','blood_magic',
            'fire_magic','water_magic','air_magic','earth_magic','energy_storage',
            'divine_favor','healing_prayers','protection_prayers','smiting_prayers'
        )
        AND quality_status='accepted'
    "
];

foreach($checks as $label=>$where){
    $n=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE {$where}")->fetchColumn();
    printf("%-45s %d".PHP_EOL,$label,$n);
}
