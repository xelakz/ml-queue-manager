<?php
//Timeout task test
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__.'/../vendor/autoload.php';
$b = rand(0,99999);
$a = function ()use($b) {
    var_dump('Timeout task start');
    file_put_contents(__DIR__.'/'.'test.log',
        'Timeout task start' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
    sleep(30);
    var_dump('Timeout task ends');
    file_put_contents(__DIR__.'/'.'test.log',
        'Timeout task ended' .$b. date('Y-m-d H:i:s'),FILE_APPEND);};
Config::set(include(__DIR__.'/Config.php'));
Queue::push('test', $a);