<?php
declare(strict_types=1);

/**
 * Conservative display fallbacks only.
 *
 * The dashboard replaces these values with normalized, trusted Kamadan quotes
 * as soon as at least two independent traders are available in the last 7 days.
 * They are intentionally kept in familiar Guild Wars trading directions.
 */
return [
    'updated_at' => '2026-08-08',
    'source' => 'Veilige fallback',
    'rates' => [
        'gold_to_ecto' => [
            'left_amount' => 100,
            'left_unit' => 'k',
            'right_amount' => 6,
            'right_unit' => 'Ecto',
            'label' => 'Platinum ↔ Ecto',
        ],
        'ecto_to_armbrace' => [
            'left_amount' => 26,
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
