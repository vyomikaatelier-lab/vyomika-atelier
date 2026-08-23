<?php

$manifest = require dirname(__DIR__).'/content/blog/manifest.php';

foreach ($manifest as $entry) {
    $slug = $entry['slug'];
    $file = dirname(__DIR__)."/content/blog/articles/{$slug}.php";
    $body = file_exists($file) ? require $file : [];
    $words = str_word_count(strip_tags($body['content'] ?? ''));
    $excerptLen = strlen($entry['excerpt'] ?? '');
    echo "{$slug}: {$words} words, excerpt=".($excerptLen ?: 'MISSING').", faqs=".count($body['faq'] ?? []).PHP_EOL;
}
