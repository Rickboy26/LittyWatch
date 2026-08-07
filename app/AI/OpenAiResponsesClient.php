<?php
declare(strict_types=1);

namespace LittyWatch\AI;

use RuntimeException;

final class OpenAiResponsesClient
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config) {}

    /** @param array<string,mixed> $payload */
    public function create(array $payload): array
    {
        $key=(string)($this->config['api_key']??'');if($key==='')throw new RuntimeException('OPENAI_API_KEY ontbreekt.');
        if(!function_exists('curl_init'))throw new RuntimeException('PHP-extensie curl ontbreekt.');
        $url=rtrim((string)($this->config['endpoint']??'https://api.openai.com/v1'),'/').'/responses';
        $ch=curl_init($url);if($ch===false)throw new RuntimeException('Kan OpenAI request niet initialiseren.');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>(int)($this->config['timeout']??35)]);
        $body=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($body===false)throw new RuntimeException('OpenAI netwerkfout: '.$error);
        $decoded=json_decode((string)$body,true);if(!is_array($decoded))throw new RuntimeException('OpenAI gaf geen geldige JSON-response.');
        if($status<200||$status>=300)throw new RuntimeException('OpenAI HTTP '.$status.': '.(string)($decoded['error']['message']??mb_substr((string)$body,0,500)));
        return $decoded;
    }

    /** @param array<string,mixed> $response */
    public function outputText(array $response): string
    {
        if(isset($response['output_text'])&&is_string($response['output_text']))return $response['output_text'];
        foreach(($response['output']??[]) as $item)foreach(($item['content']??[]) as $content)if(($content['type']??'')==='output_text'&&isset($content['text']))return (string)$content['text'];
        throw new RuntimeException('OpenAI-response bevat geen output_text.');
    }
}
