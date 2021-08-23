<?php
define('MULTILINE_QUEUE_CLI',true);
use MultilineQM\Config\Config;
require_once __DIR__.'/../vendor/autoload.php';
Config::set(include(__DIR__.'/Config.php'));
(new \MultilineQM\Console\Application())->run();
