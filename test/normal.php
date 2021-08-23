<?php
//Perform the task test normally
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;

require_once __DIR__.'/../vendor/autoload.php';
$b = rand(0,99999);
$a = function ()use($b) {
    file_put_contents(__DIR__.'/'.'test.log',
        'Start of normal task' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
    sleep(20);
    file_put_contents(__DIR__.'/'.'test.log',
        'The end of the normal task' .$b. date('Y-m-d H:i:s'),FILE_APPEND);};
Config::set(include(__DIR__.'/Config.php'));

Queue::push('test', $a,10);