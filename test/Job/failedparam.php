<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__ . '/../../vendor/autoload.php';
Config::set(include(__DIR__ . '/../Config.php'));

$msg = array("code"=>"001", "msg"=>"test code 001");
Queue::push('test', new \MultilineQMTest\Job\FailedJobParam($msg));