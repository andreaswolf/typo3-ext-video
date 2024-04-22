<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_70,
    ]);

    // Docblock names must not be imported for earlier TYPO3 versions
    $rectorConfig->importNames(true, false);
    $rectorConfig->importShortClasses(false);
};
