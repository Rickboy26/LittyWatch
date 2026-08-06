<?php
declare(strict_types=1);

/**
 * LittyWatch exchange rates.
 *
 * Pas alleen de waarden hieronder aan wanneer de Kamadan-markt verandert.
 * De website toont de verhoudingen zoals Guild Wars-traders ze gebruiken;
 * deze configuratie verandert geen opgeslagen aanbiedingen.
 */
return [
    'updated_at' => '2026-08-06 07:45',
    'source' => 'Handmatig ingesteld',
    'rates' => [
        'gold_to_ecto' => [
            'left_amount' => 100,
            'left_unit' => 'k',
            'right_amount' => 5,
            'right_unit' => 'Ecto',
            'label' => 'Platinum ↔ Ecto',
        ],
        'ecto_to_armbrace' => [
            'left_amount' => 25,
            'left_unit' => 'Ecto',
            'right_amount' => 1,
            'right_unit' => 'Arm',
            'label' => 'Ecto ↔ Armbrace',
        ],
        'ecto_to_zkey' => [
            'left_amount' => 1,
            'left_unit' => 'Ecto',
            'right_amount' => 0.8,
            'right_unit' => 'Zkey',
            'label' => 'Ecto ↔ Zaishen Key',
        ],
        'ecto_to_obby' => [
            'left_amount' => 1,
            'left_unit' => 'Ecto',
            'right_amount' => 2,
            'right_unit' => 'Obby Shard',
            'label' => 'Ecto ↔ Obsidian Shard',
        ],
    ],
];
