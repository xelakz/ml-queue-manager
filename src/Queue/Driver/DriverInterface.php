<?php

namespace MultilineQM\Queue\Driver;
use MultilineQM\Job;

/**
 * Interface DriverInterface
 * statics zet data receipt
 * task hset task details
 * delay zset id timestamp delay queue
 * reserved zset id timeout reserved queue (in progress)
 * failed hset failure queue
 */
interface DriverInterface
{
    /**
     * Set the current operation queue
     * @param $queue
     */
    public function setQueue($queue):DriverInterface;

    /**
     * Get connection
     */
    public function getConnect();


    /**
     * Close the current connection instance
     * @return mixed|void
     */
    public function close();

    /**
     * Add queue task
     * @param string $job The serialized string of the corresponding information of the task
     * @param int $delay Delay delivery time
     * @param int $timeout timeout time (s supports decimal precision to 0.001) 0 means no timeout
     * @param int $fail_number maximum number of failures
     * @param int $fail_expire failed re-delivery delay
     * @return bool
     */
    public function push(string $job, int $delay = 0, int $timeout = 0, int $fail_number = 0, $fail_expire = 3): bool;


    /**
     * Pop an executable task from the waiting queue
     */
    public function popJob();

    /**
     * Move overdue tasks to the waiting queue
     * @param int $number The number of removals The larger the number, the longer the redis blocking time
     * @return mixed
     */
    public function moveExpired($number = 50);


    /**
     * Move overtime to assign tasks to the waiting queue
     * @param int $number
     */
    public function moveExpiredReserve($number = 50);

    /**
     * Move the timeout retry task to the waiting queue
     * @param int $number
     * @return mixed
     */
    public function moveExpiredRetry($number = 50);

    /**
     * Get execution timeout id
     * @return mixed
     */
    public function popTimeoutJob();


    /**
     * Start to execute the task, remove the task in the waiting queue and set the execution information
     * @param $id
     * @return mixed
     */
    public function reReserve($id);

    /**
     * Start consumption to execute overtime tasks and return corresponding task details
     * @param $id
     * @return mixed
     */
    public function consumeTimeoutWorking($id);

    /**
     * Delete successfully executed tasks
     * @param $id
     * @return mixed
     */
    public function remove($id);

    /**
     * Add error information to the details
     * @param int $id
     * @param string $error
     * @return mixed|string
     */
    public function setErrorInfo(int $id, string $error);

    /**
     * Republish the failed task again
     * @param int $id
     * @param int $delay Retry delay time (s supports decimal precision to 0.001)
     * @return mixed|string
     */
    public function retry(int $id, int $delay = 0);

    /**
     * Record failed tasks (the number of failed retries has been reached)
     * @param int $id
     * @param string $info task collection information
     */
    public function failed(int $id,string $info);

    /**
     * Set the execution timeout time of the task in execution
     * @param $id
     * @param int $timeout 0 means no timeout (s supports decimal precision to 0.001)
     * @return int Number of values ​​added
     */
    public function setWorkingTimeout($id, $timeout = 0):int;

    /**
     * Delete failed task information
     * @param $id
     * @return int 0 delete failed 1 succeed
     */
    public function removeFailedJob($id):int;


    /**
     * Get the number of tasks
     * @param string $type
     * all
     * waiting for execution, including delivered but not allocated, allocated but not executed, delayed delivery, failed retry
     * working
     * failed
     * over completed
     * @return int
     */
    public function getCount($type ='all'):int;


    /**
     * Obtaining a list of all failed tasks will block when there are too many failed tasks
     * @return array
     */
    public function failedList():array;


    /**
     * Clear all information in the queue
     */
    public function clean();

}