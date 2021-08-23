<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__ . '/../../vendor/autoload.php';
Config::set(include(__DIR__ . '/../Config.php'));

Queue::push('test', \MultilineQMTest\Job\TimeoutJob::class);