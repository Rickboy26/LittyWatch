<?php
declare(strict_types=1);

$mode = strtolower(trim((string)(getenv('LITTYWATCH_AI_MODE') ?: 'risky')));
if (!in_array($mode, ['off', 'risky', 'all'], true)) $mode = 'risky';

return [
    'enabled' => trim((string)(getenv('OPENAI_API_KEY') ?: '')) !== '' && $mode !== 'off',
    'mode' => $mode,
    'api_key' => trim((string)(getenv('OPENAI_API_KEY') ?: '')),
    'model' => trim((string)(getenv('LITTYWATCH_AI_MODEL') ?: 'gpt-5-mini')),
    'endpoint' => trim((string)(getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1')),
    'timeout' => max(5, (int)(getenv('LITTYWATCH_AI_TIMEOUT') ?: 35)),
    'min_confidence' => max(0.0, min(1.0, (float)(getenv('LITTYWATCH_AI_MIN_CONFIDENCE') ?: 0.90))),
    'auto_apply' => filter_var(getenv('LITTYWATCH_AI_AUTO_APPLY') ?: '0', FILTER_VALIDATE_BOOL),
];
