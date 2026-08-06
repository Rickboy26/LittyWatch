<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$reflection = new ReflectionClass(\LittyWatch\Parser\Catalog::class);
if (!$reflection->hasMethod('database')) {
    fwrite(STDERR, "Catalog::database ontbreekt.\n");
    exit(1);
}

$source = (string)file_get_contents(
    dirname(__DIR__) . '/app/Parser/ParserEngine.php'
);

if (str_contains($source, 'ParserKnowledgeRepository($catalog->knowledgeBase())')) {
    fwrite(STDERR, "ParserEngine gebruikt nog KnowledgeBase in plaats van PDO.\n");
    exit(1);
}

if (!str_contains($source, 'ParserKnowledgeRepository($catalog->database())')) {
    fwrite(STDERR, "Correcte PDO-wiring ontbreekt.\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'catalog_database_method' => true,
    'parser_repository_receives_pdo' => true,
], JSON_PRETTY_PRINT) . PHP_EOL;
