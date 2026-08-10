<?php
declare(strict_types=1);

return [
    // Kamadan-listings zijn vluchtig; na 48 uur tellen ze niet meer als actief.
    'active_offer_hours' => 48,

    // Ruwe chatberichten blijven lang genoeg beschikbaar voor reparses/training,
    // maar groeien niet onbeperkt door.
    'message_retention_days' => 21,

    // Extra vangrail naast tijdsretentie. Oudste niet-handmatig beoordeelde
    // berichten worden als eerste verwijderd als dit plafond wordt overschreden.
    'max_messages' => 75000,

    // Historische structured rows per marktvariant + buy/sell binnen de bewaartermijn.
    // Active rows en handmatig beoordeelde rows worden nooit door deze cap verwijderd.
    'max_historical_offers_per_market' => 250,

    // De collector controleert maximaal eenmaal per dag of pruning nodig is.
    'auto_prune_interval_hours' => 24,
];
