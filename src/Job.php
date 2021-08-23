<?php


namespace MultilineQM;


use MultilineQM\Config\ProcessConfig;
use MultilineQM\Config\QueueConfig;

abstract class Job
{
    /**
     * Task timeout time (in seconds, after timeout, it will fail directly without retrying the task)
     * 0 means never timeout null means use queue timeout setting (queue timeout configured during delivery instead of configured time loaded by running process)
     * @var int
     */
    protected $timeout = null;

    /**
     * Maximum number of failures 0 means no retry if error occurs null means the maximum number of failures to use the queue
     * @var null
     */
    protected $fail_number = null;

    /**
     * After the task fails, it will be re-delivered after a few seconds. 0 means that it will be delivered immediately if there is an error. Null means that the queue will be delayed in seconds.
     * @var int
     */
    protected $fail_expire = null;

    /**
     * Return the timeout seconds of the queue task/0 (0 means no timeout)
     * @return int|null
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Return the maximum number of failures allowed
     * @return int|null
     */
    public function getFailNumber()
    {
        return $this->fail_number;
    }

    /**
     * Delayed retry time after failure
     * @return int|null
     */
    public function getFailExpire()
    {
        return $this->fail_expire;
    }


    /**
     * Task execution method
     * @return mixed
     */
    abstract public function handle();

    /**
     * Called after the task times out (call before the handle of the queue, unless it returns false, the fail_handle of the queue is no longer called)
     * @param array $jobInfo task details array
     */
    public function timeout_handle(array $jobInfo){
        return false;
    }

    /**
     * Called after the task fails
     * @param array $jobInfo Task details array
     * @param \Throwable $e Error exception object
     * @return mixed (Called before the handle of the queue, the fail_handle of the queue will not be called unless it returns false)
     */
    public function fail_handle(array $jobInfo,\Throwable $e)
    {
        return false;
    }

}