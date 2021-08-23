<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__.'/../vendor/autoload.php';
Config::set(include(__DIR__.'/Config.php'));
for($i=1;$i<10000;$i++){
    Queue::push('test', function ()use($i){
        echo $i."\n";
        echo "use ". ((microtime(true)) * 1000). "ms\n";
        file_put_contents(__DIR__.'/'.'test.log',
            'Start of normal task' .$i.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
    });
}