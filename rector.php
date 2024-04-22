<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_74,
    ]);

    $rectorConfig->skip([
        // disabled because this breaks PATH_site
        SensitiveConstantNameRector::class,
    ]);

    // Docblock names must not be imported for earlier TYPO3 versions
    $rectorConfig->importNames(true, false);
    $rectorConfig->importShortClasses(false);
};
