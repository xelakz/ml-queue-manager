<?php


namespace MultilineQM\Queue;


use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\QueueConfig;
use MultilineQM\Job;
use MultilineQM\Log\Log;
use MultilineQM\Queue\Driver\DriverInterface;
use MultilineQM\Serialize\JobSerialize;
use Swoole\Coroutine\Channel;

/**
 * Queue class
 * Class Queue
 * @method \MultilineQM\Queue\Driver\DriverInterface close() Close the current queue
 * @package MultilineQM\Queue
 * @see \MultilineQM\Queue\Driver\DriverInterface
 */
class Queue
{
    private $driver = null;
    private $idChannel = null;
    const WORKER_POP_TIMEOUT = 0.5; //After the task is popped up, the time interval between the execution of the distributed worker (in seconds, it will be redistributed to other workers after timeout)

    /**
     * Queue constructor.
     * @param \MultilineQM\Queue\Driver\DriverInterface $driver Queue driver
     * @param $queue queue name
     */
    public function __construct(DriverInterface $driver,$queue)
    {
        $this->driver = $driver;
        $this->driver->setQueue($queue);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->driver, $name], $arguments);
    }


    /**
     * Add job tasks to the queue
     * @param string $queue queue name
     * @param callable|Job $job task type
     * @param int $delay delay time (seconds support decimal precision to 0.001) 0 means no delay
     * @return bool
     * @throws \Exception
     */
    public static function push(string $queue,$job,int $delay = 0){
        if (is_string($job) && class_exists($job)) {
            $callJob = new $job;
        }else{
            $callJob = $job;
        }
        if($callJob instanceof Job){
            $timeout = is_null($callJob->getTimeout())?QueueConfig::queue($queue)->timeout():$callJob->getTimeout();
            $fail_number = is_null($callJob->getFailNumber())?QueueConfig::queue($queue)->fail_number():$callJob->getFailNumber();
            $fail_expire = is_null($callJob->getFailExpire())?QueueConfig::queue($queue)->fail_expire():$callJob->getFailExpire();
        }elseif(is_callable($job)) {
            $timeout = QueueConfig::queue($queue)->timeout();
            $fail_number = QueueConfig::queue($queue)->fail_number();
            $fail_expire = QueueConfig::queue($queue)->fail_expire();
        }else{
            throw new \Exception('The content of the queue task is illegal, it must be an implementation of the \MultilineQM\Job class/legal callable structure');
        }
        return  BasicsConfig::driver()->setQueue($queue)->push(JobSerialize::serialize($job),
            $delay,
            max(0,(double)$timeout),
            max(0,(int)$fail_number),
            max(0,(double)$fail_expire)
        );
    }

    /**
     * Add coroutine timing tasks (contains coroutines inside, no need to add coroutines repeatedly)
     */
    public function timerInterval(){
        $this->moveExpired();
        $this->moveExpiredReserve();
        $this->moveExpiredRetry();
    }

    /**
     * Move due tasks to the waiting queue
     */
    private function moveExpired(){
        go(function (){
            while (true){
                $ids = $this->driver->moveExpired(50);
                Log::debug('Move expired delayed tasks to the waiting queue',(array)$ids);
                if(count($ids) == 50){
                    continue;
                }
                $this->sleep();
            }
        });
    }

    /**
     * Move the distribution timeout to the waiting queue
     */
    private function moveExpiredReserve(){
        go(function (){
            while (true){
                $ids = $this->driver->moveExpiredReserve(50);
                Log::debug('Move and distribute overtime tasks to the waiting queue',(array)$ids);
                if(count($ids) == 50){
                    continue;
                }
                $this->sleep();
            }
        });
    }

    /**
     * Move failed and retry to the waiting queue
     */
    private function moveExpiredRetry()
    {
        go(function () {
            while (true) {
                $ids = $this->driver->moveExpiredRetry(50);
                Log::debug('Move the retry task to the waiting queue',(array)$ids);
                if (count($ids) == 50) {
                    continue;
                }
                $this->sleep();
            }
        });
    }

    /**
     * Timed loop to extract executable id
     * @throws \RedisException
     */
    public function popInterval(){
        $this->idChannel = new Channel(1);
        $this->popJob();
        $this->popTimeoutJob();
    }



    /**
     * Get the queue id and put it in idChannel
     * @return mixed|null
     * @throws \RedisException
     */
    private function popJob(){
        go(function (){
            do{
                $result = $this->driver->popJob();
                $result && $this->idChannel->push(['type'=>'job','id'=>$result]);
                Log::debug('Query task',[$result]);
                $this->sleep();
            }while(true);
        });
    }

    /**
     * Get the execution timeout task id and put it in idChannel
     * @return mixed
     * @throws \RedisException
     */
    private function popTimeoutJob(){
        go(function (){
            do{
                $result = $this->driver->popTimeoutJob();
                $result && $this->idChannel->push(['type'=>'timeoutJob','id'=>$result]);
                Log::debug('Query overtime tasks',[$result]);
                $this->sleep();
            }while(true);
        });
    }

    /**
     * Block to obtain executable id (need to be executed in the coroutine)
     * @return array ['type'=>'','id'=>'']
     */
    public function pop(){
        return $this->idChannel->pop();
    }

    /**
     * For consumption tasks, remove the corresponding tasks in the waiting queue and set execution information
     * @param $id
     * @return mixed
     * @throws \RedisException
     */
    public function consumeJob($id){
        $info =  $this->driver->reReserve($id);
        if($info){
           $info['job'] = JobSerialize::unSerialize($info['job']);
        }
        return $info;
    }

    /**
     * Consumption overtime task
     * @param $id
     * @return mixed
     * @throws \RedisException
     */
    public function consumeTimeoutJob($id){
        $info = $this->driver->consumeTimeoutWorking($id);
        if($info){
            $info['job'] = JobSerialize::unSerialize($info['job']);
        }
        return $info;
    }

    /**
     * Republish the failed task again
     * @param int $id task id
     * @param string $error task error message
     * @param int $delay Retry delay time (s allows decimal precision to 0.001)
     * @return bool
     * @throws \RedisException
     */
    public function retry(int $id, string $error,int $delay = 0){
        return $this->driver->setErrorInfo($id,$error."\n") && $this->driver->retry($id,$delay);
    }

    /**
     * Delete successfully executed task information
     * @param int $id
     * @return mixed
     * @throws \RedisException
     */
    public function remove(int $id){
        return $this->driver->remove($id);
    }

    /**
     * Mission failure, record and keep
     * @param int $id
     * @param array $info
     * @param string $error error message
     * @return bool
     */
    public function failed(int $id, array $info,string $error){
        $info['error_info'] .= $error."\n";
        return $this->driver->failed($id,JobSerialize::serialize($info));
    }



    /**
     * Get the number of tasks in a specific queue
     * @param $queue
     * @param $type
     * all
     * waiting for execution, including delivered but not allocated, allocated but not executed, delayed delivery, failed retry
     * working
     * failed
     * over completed
     * @return int
     */
    static public function getCount($queue,$type): int
    {
        return  BasicsConfig::driver()->setQueue($queue)->getCount($type);
    }

    /**
     * Remove failed tasks
     * @param $id
     */
    static public function removeFailedJob($queue,$id){
        return BasicsConfig::driver()->setQueue($queue)->removeFailedJob($id);
    }

    /**
     * Clear all tasks in the queue
     */
    static public function cleanJob($queue){
        return BasicsConfig::driver()->setQueue($queue)->clean();
    }

    /**
     * Get a list of failed tasks
     * @param $queue
     * @return array
     */
    static public function failedList($queue){
        $result = BasicsConfig::driver()->setQueue($queue)->failedList();
        foreach ($result as &$value){
            $value = JobSerialize::unSerialize($value);
        }
        return $result;
    }

    /**
     * Set the execution timeout time of the task in execution
     * @param $id
     * @param null|int $timeout （s Support decimals, precision to 0.001）
     */
    public function setWorkingTimeout($id,$timeout = null){
        return $this->driver->setWorkingTimeout($id,$timeout);
    }

    /**
     * Coroutine sleep will let out of the coroutine
     */
    private function sleep(){
        \Swoole\Coroutine\System::sleep(QueueConfig::queue()->sleep_seconds());
    }


}