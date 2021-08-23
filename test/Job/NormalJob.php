<?php
namespace MultilineQMTest\Job;


class NormalJob extends \MultilineQM\Job{

    public function handle()
    {
        $b =rand(1,999);
        var_dump('The normal task starts to execute');
        file_put_contents(__DIR__ . '/' . 'test.log',
            'Start of normal task' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        sleep(15);
        var_dump('End of normal task');
        file_put_contents(__DIR__ . '/' . 'test.log',
            'End of normal task' .$b. date('Y-m-d H:i:s'),FILE_APPEND);
    }
}