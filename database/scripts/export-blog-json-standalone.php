<?php

/**
 * Standalone export — no Composer autoload required.
 * Usage: php database/scripts/export-blog-json-standalone.php
 */

$root = dirname(__DIR__, 2);

$categories = [
    ['slug' => 'pvd-design', 'label' => 'PVD Design'],
    ['slug' => 'doors', 'label' => 'Doors'],
    ['slug' => 'partitions', 'label' => 'Partitions'],
    ['slug' => 'mirrors', 'label' => 'Mirrors'],
    ['slug' => 'furniture', 'label' => 'Furniture'],
    ['slug' => 'railings', 'label' => 'Railings'],
    ['slug' => 'corten-steel', 'label' => 'Corten Steel'],
    ['slug' => 'projects', 'label' => 'Projects'],
    ['slug' => 'exhibitions', 'label' => 'Exhibitions'],
];

$posts = require $root . '/database/data/blog-catalog.php';

function categorySlug(string $category, array $categories): string
{
    foreach ($categories as $cat) {
        if ($cat['label'] === $category || $cat['slug'] === $category) {
            return $cat['slug'];
        }
    }

    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($category)));
}

function readingTimeMinutes(?string $content, ?int $stored = null): int
{
    if ($stored !== null && $stored > 0) {
        return $stored;
    }

    $text = trim(strip_tags($content ?? ''));
    if ($text === '') {
        return 1;
    }

    return max(1, (int) ceil(str_word_count($text) / 200));
}

$exportPosts = array_map(function (array $post) use ($categories) {
    $published = ! empty($post['published_at'])
        ? date('j F Y', strtotime($post['published_at']))
        : '';

    return [
        ...$post,
        'date' => $published,
        'reading_time_minutes' => readingTimeMinutes(
            $post['content'] ?? '',
            $post['reading_time_minutes'] ?? null
        ),
        'category_slug' => categorySlug($post['category'] ?? '', $categories),
    ];
}, $posts);

$payload = [
    'meta_title' => 'Ideas, Materials & Projects — Blog | Vyomika Atelier LLP',
    'meta_description' => 'Design ideas, material guides, and project stories on PVD partitions, stainless doors, Corten steel, and bespoke metalwork from Vyomika Atelier LLP, Mumbai.',
    'index' => [
        'label' => 'Journal',
        'title' => 'Ideas, Materials & Projects',
        'subtitle' => 'PVD finishes, partition design, Corten steel, and fabrication insights from our Mumbai studio.',
    ],
    'categories' => $categories,
    'posts' => $exportPosts,
];

$path = $root . '/public/data/blog.json';
file_put_contents(
    $path,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

echo "Wrote {$path}\n";
