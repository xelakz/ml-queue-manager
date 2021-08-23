<?php
namespace MultilineQMTest\Job;

class TimeoutJob extends \MultilineQM\Job
{
    /**
     * Task timeout period (after timeout, it will fail directly without retrying the task)
     * 0 means never timeout null means use queue timeout setting
     * @var int
     */
    protected $timeout = 2;//10

    public function handle()
    {
        var_dump('Timeout task start');
        $b =rand(1,999);
        file_put_contents(__DIR__.'/'.'test.log',
            'Timeout task start' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        sleep(30);
        var_dump('Timeout task ends');
        file_put_contents(__DIR__.'/'.'test.log',
            'Timeout task ended' .$b. date('Y-m-d H:i:s'),FILE_APPEND);
    }

    public function timeout_handle(array $jobInfo)
    {
        var_dump($jobInfo);
        return true;//The configuration handle will not be called
// return false;//The configuration handle will be called
    }
}