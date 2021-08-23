<?php
namespace MultilineQMTest\Job;

// use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;

class FailedJobParam extends \MultilineQM\Job{

    // protected $fail_expire = null;//Failure retry time as configured
    protected $fail_expire = 10;

    //protected $fail_number = null; //The maximum number of failures is configured
    protected $fail_number = 4; //Maximum number of failures 4 times

    private $message = array();

    public function __construct($message)
    {
        $this->message = $message;
    }
    public function handle()
    {
        var_dump($this->message);
        // Log::info('FailedJobParam: ' . json_encode($this->message));
        OutPut::normal('FailedJobParam: ' . json_encode($this->message) . "\n");

        // $b = rand(0,999);
        // var_dump('Abnormal task execution');
        // file_put_contents(__DIR__ . '/' . 'test.log',
        //     'Exception task started' .$b.'-'. date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        // throw new \Exception('Throw an exception directly');
    }

    public function fail_handle(array $jobInfo, \Throwable $e)
    {
        var_dump($jobInfo);
        var_dump($e->getMessage());
        return true;
        // return false; //Will call the configuration handle
    }
}