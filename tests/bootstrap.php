<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$autoload = $packageRoot . '/vendor/autoload.php';
if (! is_file($autoload)) {
    $autoload = dirname($packageRoot, 2) . '/vendor/autoload.php';
}
if (! is_file($autoload)) {
    throw new RuntimeException('Install the package dependencies with Composer before running tests.');
}

$loader = require $autoload;
$loader->addPsr4('Fayne\\Promotion\\', $packageRoot . '/src', true);
$loader->addPsr4('Fayne\\Promotion\\Tests\\', $packageRoot . '/tests', true);
