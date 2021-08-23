<?php
//Failed task test
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__.'/../vendor/autoload.php';
$b = rand(0,99999);
$a = function ()use ($b) {
    file_put_contents(__DIR__.'/'.'test.log',
        'Abnormal task start' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
    throw new \Exception('Throw an exception directly');
};
Config::set(include(__DIR__.'/Config.php'));

Queue::push('test', $a);