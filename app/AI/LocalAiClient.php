<?php
declare(strict_types=1);

namespace LittyWatch\AI;

use RuntimeException;
use LittyWatch\AI\Schema\TradeParseSchema;

final class LocalAiClient
{
    public function __construct(
        private readonly string $baseUrl = 'http://127.0.0.1:8081'
    ) {}

    public function complete(
        string $prompt,
        int $maxTokens = 300,
        float $temperature = 0.0
    ): array {
        /*
         * Force llama.cpp to generate exactly this JSON shape.
         *
         * We intentionally keep the AI output semantic/raw:
         * canonical Guild Wars item/attribute resolution belongs to LittyWatch.
         */
        $schema = TradeParseSchema::jsonSchema();

        $payload = [
            'prompt' => $prompt,
            'n_predict' => min($maxTokens, 800),
            'temperature' => $temperature,
            'cache_prompt' => true,

            // llama.cpp converts JSON Schema to a constrained grammar.
            'json_schema' => $schema,

            'stop' => [
                '</s>',
                '<|im_end|>',
                '<|endoftext|>',
            ],
        ];

        $ch = curl_init($this->baseUrl . '/completion');

        if ($ch === false) {
            throw new RuntimeException('Kon curl niet initialiseren.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException(
                'AI request mislukt: ' . $error
            );
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                "AI server HTTP {$status}: {$response}"
            );
        }

        try {
            $decoded = json_decode(
                $response,
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'Ongeldig antwoord van AI-server: ' . $e->getMessage()
            );
        }

        $content = trim((string)($decoded['content'] ?? ''));

        if ($content === '') {
            throw new RuntimeException(
                'AI-server gaf geen content terug.'
            );
        }

        /*
         * Grammar should guarantee JSON, but validate anyway.
         * Never allow malformed AI output farther into LittyWatch.
         */
        try {
            $parsed = json_decode(
                $content,
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'AI gaf ongeldig JSON terug: ' .
                $e->getMessage() .
                ' | output=' .
                substr($content, 0, 500)
            );
        }

        if (
            !is_array($parsed) ||
            !isset($parsed['offers']) ||
            !is_array($parsed['offers'])
        ) {
            throw new RuntimeException(
                'AI JSON bevat geen geldige offers-array.'
            );
        }

        return [
            'content' => $content,
            'parsed' => $parsed,
            'raw' => $decoded,
        ];
    }
}
