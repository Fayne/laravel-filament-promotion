<?php

putenv('COMPOSER_VENDOR_DIR=' . dirname(__DIR__) . '/vendor');
$_ENV['COMPOSER_VENDOR_DIR'] = dirname(__DIR__) . '/vendor';

$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';

$loader->addPsr4('Fayne\\Promotion\\', dirname(__DIR__) . '/src');
