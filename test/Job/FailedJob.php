<?php
namespace MultilineQMTest\Job;

class FailedJob extends \MultilineQM\Job{

    // protected $fail_expire = null;//Failure retry time as configured
    protected $fail_expire = 10;

    //protected $fail_number = null; //The maximum number of failures is configured
    protected $fail_number = 4; //Maximum number of failures 4 times

    public function handle()
    {
        $b = rand(0,999);
        var_dump('Abnormal task execution');
        file_put_contents(__DIR__ . '/' . 'test.log',
            'Exception task started' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        throw new \Exception('Throw an exception directly');
    }

    public function fail_handle(array $jobInfo, \Throwable $e)
    {
        var_dump($jobInfo);
        var_dump($e->getMessage());
        return true;
        // return false; //Will call the configuration handle
    }
}