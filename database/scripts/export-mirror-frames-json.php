<?php
$root = dirname(__DIR__, 2);
$config = require $root . '/config/mirror-frames.php';
$config['products'] = require $root . '/database/data/mirror-frames-catalog.php';
file_put_contents(
    $root . '/public/data/mirror-frames.json',
    json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "Exported mirror-frames.json\n";
