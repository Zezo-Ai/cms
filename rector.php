<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/lib',
        __DIR__ . '/src',
    ])
    ->withSkip([
        __DIR__ . '/src/icons/index.php',
    ])
    ->withPhpSets(php56: true);