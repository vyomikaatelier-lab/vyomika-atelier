<?php
$root = dirname(__DIR__, 2);
$data = require $root . '/config/railings.php';
file_put_contents(
    $root . '/public/data/railings.json',
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "Exported railings.json\n";
