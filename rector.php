<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/lib',
        __DIR__ . '/src',
        __DIR__ . '/tests/functional',
        __DIR__ . '/tests/unit',
    ])
    ->withSkip([
        __DIR__ . '/src/icons/index.php',
    ])
    ->withPhpSets(php70: true);
