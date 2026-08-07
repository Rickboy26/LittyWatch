<?php
declare(strict_types=1);

namespace LittyWatch\AI;

use RuntimeException;

final class AiMarketValidator
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly OpenAiResponsesClient $client, private readonly array $config) {}

    /** @param array<string,mixed> $context */
    public function validate(array $context): array
    {
        $schema=[
            'type'=>'object','additionalProperties'=>false,'required'=>['decision','confidence','reason','parser_valid','correction'],
            'properties'=>[
                'decision'=>['type'=>'string','enum'=>['accept','reject','correct','review']],
                'confidence'=>['type'=>'number','minimum'=>0,'maximum'=>1],
                'reason'=>['type'=>'string'],
                'parser_valid'=>['type'=>'boolean'],
                'correction'=>['type'=>'object','additionalProperties'=>false,'required'=>['trade_type','item','price_amount','price_currency','price_basis','quantity','unit_price_ecto'], 'properties'=>[
                    'trade_type'=>['type'=>['string','null'],'enum'=>['buy','sell','trade',null]],
                    'item'=>['type'=>['string','null']],
                    'price_amount'=>['type'=>['number','null']],
                    'price_currency'=>['type'=>['string','null'],'enum'=>['e','a','k',null]],
                    'price_basis'=>['type'=>['string','null'],'enum'=>['each','stack','total','ratio','exchange','unknown',null]],
                    'quantity'=>['type'=>['number','null']],
                    'unit_price_ecto'=>['type'=>['number','null']],
                ]],
            ],
        ];
        $system='You are the market-quality verifier for LittyWatch, a Guild Wars 1 Kamadan trade parser. Judge only what the original advertisement supports. Prices can be ecto (e), armbraces (a), or platinum (k). Never attach a price from a different item segment. Respect explicit /ea, /each, /stk, stack, xN, ratios, and totals. Known stackable commodities may be quoted per 250-stack even when the word stack is omitted, but only when item context supports it. If the ad is ambiguous, choose review instead of inventing a value. Historical market prices are only a sanity signal, never proof. Return a correction only when strongly supported by the ad; otherwise use null fields.';
        $user=[
            'original_message'=>(string)($context['message']??''),'player'=>(string)($context['player']??''),'posted_at'=>(string)($context['posted_at']??''),
            'parser_offer'=>['trade_type'=>$context['trade_type']??null,'item'=>$context['item']??null,'market_key'=>$context['normalized_market_key']??$context['market_key']??null,'raw_segment'=>$context['raw_segment']??null,'quantity'=>$context['quantity']??null,'price_amount'=>$context['price_amount']??null,'price_currency'=>$context['price_currency']??null,'price_ecto'=>$context['price_ecto']??null,'unit_price_ecto'=>$context['unit_price_ecto']??null,'price_basis'=>$context['price_basis']??null,'confidence'=>$context['confidence']??null,'quality_status'=>$context['quality_status']??null,'quality_reason'=>$context['quality_reason']??null],
            'sibling_offers'=>$context['siblings']??[],'market_context'=>['samples'=>$context['market_samples']??0,'median_unit_ecto'=>$context['median_unit_ecto']??null,'ecto_per_armbrace'=>function_exists('lw_ecto_per_armbrace')?\lw_ecto_per_armbrace():25.0,'platinum_per_ecto'=>15.0],
            'item_profile'=>json_decode((string)($context['profile_json']??'{}'),true)?:new \stdClass(),
        ];
        $payload=['model'=>(string)($this->config['model']??'gpt-5-mini'),'instructions'=>$system,'input'=>json_encode($user,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'text'=>['format'=>['type'=>'json_schema','name'=>'littywatch_market_validation','description'=>'Strict verification of one parsed GW1 market offer.','strict'=>true,'schema'=>$schema]]];
        $response=$this->client->create($payload);$text=$this->client->outputText($response);$result=json_decode($text,true);if(!is_array($result))throw new RuntimeException('AI-output voldoet niet aan JSON-formaat.');
        return ['result'=>$result,'response_id'=>isset($response['id'])?(string)$response['id']:null,'raw_json'=>json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}'];
    }
}
