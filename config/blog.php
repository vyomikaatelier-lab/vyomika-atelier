<?php

/**
 * Blog — index meta, categories, and article catalog.
 */
$posts = require database_path('data/blog-catalog.php');

return [
    'meta_title' => 'Ideas, Materials & Projects — Blog | Vyomika Atelier LLP',
    'meta_description' => 'Design ideas, material guides, and project stories on PVD partitions, stainless doors, Corten steel, and bespoke metalwork from Vyomika Atelier LLP, Mumbai.',

    'index' => [
        'label' => 'Journal',
        'title' => 'Ideas, Materials & Projects',
        'subtitle' => 'PVD finishes, partition design, Corten steel, and fabrication insights from our Mumbai studio.',
    ],

    'categories' => [
        ['slug' => 'pvd-design', 'label' => 'PVD Design'],
        ['slug' => 'doors', 'label' => 'Doors'],
        ['slug' => 'partitions', 'label' => 'Partitions'],
        ['slug' => 'mirrors', 'label' => 'Mirrors'],
        ['slug' => 'furniture', 'label' => 'Furniture'],
        ['slug' => 'railings', 'label' => 'Railings'],
        ['slug' => 'corten-steel', 'label' => 'Corten Steel'],
        ['slug' => 'projects', 'label' => 'Projects'],
        ['slug' => 'exhibitions', 'label' => 'Exhibitions'],
    ],

    'posts' => $posts,
];
