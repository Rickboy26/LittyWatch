<?php
declare(strict_types=1);

namespace LittyWatch\AI\Schema;

final class TradeParseSchema
{
    public static function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'offers' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'trade_type' => [
                                'type' => 'string',
                                'enum' => ['sell', 'buy', 'trade', 'unknown'],
                            ],
                            'item_text' => [
                                'type' => ['string', 'null'],
                            ],
                            'requirement' => [
                                'type' => ['integer', 'null'],
                            ],
                            'attribute_token' => [
                                'type' => ['string', 'null'],
                            ],
                            'price_amount' => [
                                'type' => ['number', 'null'],
                            ],
                            'price_currency' => [
                                'type' => ['string', 'null'],
                                'enum' => ['e', 'a', 'k', null],
                            ],
                            'oldschool' => [
                                'type' => 'boolean',
                            ],
                            'inscribable' => [
                                'type' => 'boolean',
                            ],
                            'mods' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'gold_value' => [
                                'type' => ['integer', 'null'],
                            ],
                        ],
                        'required' => [
                            'trade_type',
                            'item_text',
                            'requirement',
                            'attribute_token',
                            'price_amount',
                            'price_currency',
                            'oldschool',
                            'inscribable',
                            'mods',
                            'gold_value',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['offers'],
            'additionalProperties' => false,
        ];
    }

    public static function prompt(): string
    {
        return <<<'PROMPT'
/no_think
You are LittyWatch's Guild Wars 1 Kamadan trade interpreter.

Extract every distinct offer or variant.

Interpret structure only. Do not invent missing information.

RULES:
- WTS = sell.
- WTB = buy.
- WTT = trade.
- q9/r9 means requirement 9.
- Multiple attributes belonging to one requirement create multiple variants.
- A variant may inherit the previous concrete item.
- A variant may inherit its requirement when appropriate.
- Preserve attribute shorthand as written.
- OS means oldschool=true.
- ins, insc and inscribable mean inscribable=true.
- Weapon modifiers such as 15^50, 19<50, +30, -2ws and 20/20 belong in mods.
- 400gv means gold_value=400. GV is NOT a price currency.
- Trade prices use e, a or k.
- Do not treat an item name as an attribute.
- Do not treat a requirement as an attribute.
- Do not treat a modifier as a price.
- Do not canonicalize item names. Keep item_text close to the message.
- offers may only be empty when the message contains no identifiable trade offer.

EXAMPLE:
MESSAGE:
WTS frog q9 insp SR // q11 es q13 FC q13 spaw

OUTPUT:
{"offers":[
{"trade_type":"sell","item_text":"frog","requirement":9,"attribute_token":"insp","price_amount":null,"price_currency":null,"oldschool":false,"inscribable":false,"mods":[],"gold_value":null},
{"trade_type":"sell","item_text":"frog","requirement":9,"attribute_token":"SR","price_amount":null,"price_currency":null,"oldschool":false,"inscribable":false,"mods":[],"gold_value":null},
{"trade_type":"sell","item_text":"frog","requirement":11,"attribute_token":"es","price_amount":null,"price_currency":null,"oldschool":false,"inscribable":false,"mods":[],"gold_value":null},
{"trade_type":"sell","item_text":"frog","requirement":13,"attribute_token":"FC","price_amount":null,"price_currency":null,"oldschool":false,"inscribable":false,"mods":[],"gold_value":null},
{"trade_type":"sell","item_text":"frog","requirement":13,"attribute_token":"spaw","price_amount":null,"price_currency":null,"oldschool":false,"inscribable":false,"mods":[],"gold_value":null}
]}

MESSAGE:
__MESSAGE__

OUTPUT:
PROMPT;
    }
}
