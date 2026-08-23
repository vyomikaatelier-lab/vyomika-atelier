<?php

/**
 * Recalculate blog metrics from source files.
 * Run: php database/scripts/blog-metrics-from-source.php
 */

$manifest = require __DIR__.'/../content/blog/manifest.php';
$articlesDir = __DIR__.'/../content/blog/articles';
$global = array_values(array_filter($manifest, fn ($e) => ($e['locale'] ?? null) === null));

$rows = [];

foreach ($global as $entry) {
    $slug = $entry['slug'];
    $file = $articlesDir.'/'.$slug.'.php';
    $article = is_file($file) ? require $file : ['content' => '', 'faq' => []];
    $html = (string) ($article['content'] ?? '');

    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    $wordCount = $text === '' ? 0 : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    $h2Count = preg_match_all('/<h2[^>]*>/i', $html);
    $faqCount = count($article['faq'] ?? []);
    $internalLinks = preg_match_all('#href="/(?:blog|studio|shop|railings|corten-steel|projects|contact|professionals)[^"]*"#', $html);

    $image = (string) ($entry['image'] ?? '');
    $heroReady = 'owner required';
    if ($image !== '' && ! str_contains($image, 'delhiduniya.com') && ! str_contains($image, 'unsplash.com')) {
        if (str_contains($image, 'campaign-partitions')) {
            $heroReady = 'broken URL';
        } elseif (str_starts_with($image, '/images/') || str_contains($image, 'vyomikaatelier.com/assets/')) {
            $heroReady = 'self-hosted';
        } else {
            $heroReady = 'external';
        }
    } elseif (str_contains($image, 'delhiduniya.com')) {
        $heroReady = 'delhiduniya hotlink';
    }

    $rows[] = [
        'slug' => $slug,
        'words' => $wordCount,
        'h2' => $h2Count,
        'faq' => $faqCount,
        'links' => $internalLinks,
        'status' => $entry['status'] ?? 'draft',
        'hero' => $heroReady,
        'keyword' => $entry['primary_keyword'] ?? '',
    ];
}

$out = __DIR__.'/../../docs/blog-international-seo/metrics-from-source.json';
file_put_contents($out, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Metrics for ".count($rows)." global articles written to docs/blog-international-seo/metrics-from-source.json\n";
printf("%-55s %5s %3s %3s %5s %10s %12s\n", 'slug', 'words', 'h2', 'faq', 'links', 'status', 'hero');
foreach ($rows as $r) {
    printf("%-55s %5d %3d %3d %5d %10s %12s\n", $r['slug'], $r['words'], $r['h2'], $r['faq'], $r['links'], $r['status'], $r['hero']);
}
