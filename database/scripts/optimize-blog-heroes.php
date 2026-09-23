<?php

/**
 * One-off blog hero optimizer — WebP + landscape card crops (no upscaling).
 * Usage: php database/scripts/optimize-blog-heroes.php
 */

$base = dirname(__DIR__, 2).'/public/images/blog/heroes';

$jobs = [
    [
        'src' => 'glass-partitions-open-plan-hero.jpg',
        'webp' => true,
        'card' => ['width' => 768, 'height' => 432, 'suffix' => '-card', 'focus_y' => 0.42],
    ],
    [
        'src' => 'pvd-coating-explained-hero.jpg',
        'webp' => true,
        'card' => ['width' => 1024, 'height' => 576, 'suffix' => '-card', 'focus_y' => 0.5],
    ],
    [
        'src' => 'corten-steel-modern-facades-hero.jpg',
        'webp' => true,
    ],
];

/** @return array{width:int,height:int,type:int}|null */
function loadImage(string $path): ?array
{
    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }

    $type = $info[2];
    $loader = match ($type) {
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
        default => null,
    };

    if ($loader === null || ! function_exists($loader)) {
        return null;
    }

    $im = @$loader($path);
    if ($im === false) {
        return null;
    }

    return ['im' => $im, 'width' => $info[0], 'height' => $info[1], 'type' => $type];
}

function saveJpeg($im, string $path, int $quality = 85): bool
{
    return imagejpeg($im, $path, $quality);
}

function saveWebp($im, string $path, int $quality = 82): bool
{
    if (! function_exists('imagewebp')) {
        return false;
    }

    return imagewebp($im, $path, $quality);
}

/** Center-weighted crop to exact dimensions without upscaling. */
function cropToFit($src, int $srcW, int $srcH, int $targetW, int $targetH, float $focusY = 0.5)
{
    $scale = max($targetW / $srcW, $targetH / $srcH);
    $cropW = (int) min($srcW, round($targetW / $scale));
    $cropH = (int) min($srcH, round($targetH / $scale));

    $srcX = (int) max(0, ($srcW - $cropW) / 2);
    $srcY = (int) max(0, min($srcH - $cropH, ($srcH - $cropH) * $focusY));

    $dest = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($dest, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);

    return $dest;
}

$report = [];

foreach ($jobs as $job) {
    $srcPath = $base.'/'.$job['src'];
    if (! is_file($srcPath)) {
        fwrite(STDERR, "Missing: {$srcPath}\n");
        exit(1);
    }

    $loaded = loadImage($srcPath);
    if ($loaded === null) {
        fwrite(STDERR, "Cannot load: {$srcPath}\n");
        exit(1);
    }

    $basename = pathinfo($job['src'], PATHINFO_FILENAME);
    $srcSize = filesize($srcPath);

    // WebP at original dimensions
    if ($job['webp'] ?? false) {
        $webpPath = $base.'/'.$basename.'.webp';
        $before = is_file($webpPath) ? filesize($webpPath) : 0;
        saveWebp($loaded['im'], $webpPath);
        $report[] = [
            'file' => basename($webpPath),
            'dimensions' => "{$loaded['width']}×{$loaded['height']}",
            'before_kb' => round($before / 1024, 1),
            'after_kb' => round(filesize($webpPath) / 1024, 1),
        ];
    }

    // Landscape card crop
    if (isset($job['card'])) {
        $card = $job['card'];
        $cardW = min($card['width'], $loaded['width']);
        $cardH = min($card['height'], $loaded['height']);

        $cropped = cropToFit(
            $loaded['im'],
            $loaded['width'],
            $loaded['height'],
            $cardW,
            $cardH,
            $card['focus_y'] ?? 0.5
        );

        $cardJpg = $base.'/'.$basename.$card['suffix'].'.jpg';
        $cardWebp = $base.'/'.$basename.$card['suffix'].'.webp';

        $beforeJpg = is_file($cardJpg) ? filesize($cardJpg) : 0;
        saveJpeg($cropped, $cardJpg);
        saveWebp($cropped, $cardWebp);

        $report[] = [
            'file' => basename($cardJpg),
            'dimensions' => "{$cardW}×{$cardH}",
            'before_kb' => round($beforeJpg / 1024, 1),
            'after_kb' => round(filesize($cardJpg) / 1024, 1),
        ];
        $report[] = [
            'file' => basename($cardWebp),
            'dimensions' => "{$cardW}×{$cardH}",
            'before_kb' => 0,
            'after_kb' => round(filesize($cardWebp) / 1024, 1),
        ];

        imagedestroy($cropped);
    }

    $report[] = [
        'file' => $job['src'],
        'dimensions' => "{$loaded['width']}×{$loaded['height']}",
        'before_kb' => round($srcSize / 1024, 1),
        'after_kb' => round($srcSize / 1024, 1),
        'note' => 'original preserved',
    ];

    imagedestroy($loaded['im']);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
