<?php


namespace MultilineQM\Queue\Driver;


use MultilineQM\Config\BasicsConfig;
use MultilineQM\Queue\Queue;
use Swoole\Coroutine;

/**
 * The redis queue driver redis version must be >=3.0.2
 * Class Redis
 * @package MultilineQM\Queue\Driver
 */
class Redis implements DriverInterface
{
    protected $connect = null;

    protected $config = [];

    protected $scriptHash = array();

    protected $scriptError = [];

    protected $queue = null;

    const TASK_NUMBER ='task_number'; //Number of queue tasks key name string type

    const TASK_OVER ='task_over'; //The task has been completed

    const INFO ='info:'; //Queue details key name (hash type)

    const DELAYED ='delayed';//Delayed queue key name (zset type) stores the id of the delayed task and the corresponding timestamp

    const WAITING ='waiting';//The key name of the queue to be executed (list type) stores the id of the task to be executed

    const RESERVE ='reserve';//Allocated reserved queue key name (zset type) to store allocated worker task id and worker timeout reception timestamp

    const WORKING ='working';//The name of the queue in execution (zset type) stores the task id of the worker executed and the execution timeout time of the worker

    const RETRY ='retry';//Retry queue (zset type) storage failure requires retry task id and re-delivery timestamp

    const FAILED ='failed';//Failed task queue (hash type stores the serialization information of failed tasks)

    /**
     * Redis constructor.
     * @param $host redis address
     * @param int $port port
     * @param string $password password
     * @param string $database database default is 0
     * @param string $prefix prefix
     */
    public function __construct($host, $port = 6379, $password = '', $database = "0", $prefix = 'multilinequeue')
    {
        $this->config = [
            'host' => $host,
            'port' => $port,
            'password' => $password,
            'database' => $database,
            'prefix' => $prefix,
        ];
    }

    /**
     * Set the current operation queue
     * @param $queue
     * @return Redis
     */
    public function setQueue($queue): DriverInterface
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Connect to redis
     * @return \Redis
     */
    public function getConnect()
    {
        //In the coroutine environment, one coroutine has one connection to prevent the shared connection data from being disordered
        if (class_exists('Swoole\Coroutine') && Coroutine::getCid() > 0) {
            return isset(Coroutine::getContext()[\Redis::class])
                ? Coroutine::getContext()[\Redis::class] : (Coroutine::getContext()[\Redis::class] = $this->connection());
        }
        if (!$this->connect) {
            $this->connect = $this->connection();
        }
        return $this->connect;
    }

    /**
     * Connect to redis
     * @return \Redis
     */
    private function connection()
    {
        $connect = new \Redis();
        $connect->connect($this->config['host'], $this->config['port']);
        $this->config['password'] && $connect->auth($this->config['password']);
        return $connect;
    }

    /**
     * Close the current connection instance
     * @return mixed|void
     */
    public function close()
    {
        $this->getConnect()->close();
        if (class_exists('Swoole\Coroutine') && Coroutine::getCid() > 0) {
            unset(Coroutine::getContext()[\Redis::class]);
        } else {
            $this->connect = null;
        }
    }

    /**
     * Add queue task
     * @param string $job The serialized string of the corresponding information of the task
     * @param int $delay Delay delivery time
     * @param int $timeout timeout time 0 means no timeout
     * @param int $fail_number number of failed retries
     * @param int $fail_expire failed re-delivery delay
     * @return bool
     */
    public function push(string $job, int $delay = 0, int $timeout = 0, int $fail_number = 0, $fail_expire = 3): bool
    {
        $script = <<<SCRIPT
local id = redis.call('incr', KEYS[1])
redis.call('hMset',KEYS[2]..id,'job',ARGV[1],'create_time',ARGV[2],'exec_number',0,'worker_id','','start_time',0,'timeout',ARGV[3],'fail_number',ARGV[4],'fail_expire',ARGV[5],'error_info','')
if (ARGV[6] > ARGV[2]) then
redis.call('zAdd',KEYS[3],ARGV[6],id)
else
redis.call('rPush',KEYS[4],id)
end
return id
SCRIPT;
        $time = microtime(true);
        return $this->eval($script, [
            $this->getKey(self::TASK_NUMBER),
            $this->getKey(self::INFO),
            $this->getKey(self::DELAYED),
            $this->getKey(self::WAITING),
            $job,
            $time,
            $timeout,
            $fail_number,
            $fail_expire,
            $delay+$time
        ], 4);
        return true;
    }


    /**
     * Pop an executable task from the waiting queue
     * @throws \RedisException
     */
    public function popJob()
    {
        $script = <<<SCRIPT
-- Pop up a task from the WAITING head of the waiting queue
local id = redis.call('lpop', KEYS[1])
if (id) then
    -- Add to the reserved set RESERVE and set the timeout redistribution timestamp
    redis.call('zAdd', KEYS[2], ARGV[1], id)
    -- Task details INFO set the task status to be taken out and set the time to take out
    redis.call('hSet',KEYS[3]..id,'pop_time',ARGV[2])
end  
return id
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::WAITING),
            $this->getKey(self::RESERVE),
            $this->getKey(self::INFO),
            microtime(true) + Queue::WORKER_POP_TIMEOUT,
            microtime(true)
        ], 3);
    }

    /**
     * Move overdue tasks to the waiting queue
     * @param int $number The number of removals The larger the number, the longer the redis blocking time
     * @return mixed
     * @throws \RedisException
     */
    public function moveExpired($number = 50)
    {
        $script = <<<SCRIPT
-- Pop up number tasks from the delayed collection DELAYED
local ids = redis.call('zRangeByScore', KEYS[1], '-inf' , ARGV[1], 'LIMIT' , 0 ,ARGV[2])
if (#ids ~= 0) then
   -- Submit the task id to the end of the push list WAITING
   redis.call('rPush',KEYS[2], unpack(ids))
   -- Remove the corresponding id from the delay set DELAYED
   redis.call('zRem',KEYS[1], unpack(ids))
end  
return ids
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::DELAYED),
            $this->getKey(self::WAITING),
            microtime(true),
            $number
        ], 2);
    }


    /**
     * Move overtime to assign tasks to the waiting queue
     * @param int $number
     */
    public function moveExpiredReserve($number = 50)
    {
        $script = <<<SCRIPT
-- Pop up number tasks from the allocation delay set RESERVE
local ids = redis.call('zRangeByScore', KEYS[1], 1 , ARGV[1], 'LIMIT' , 0 ,ARGV[2])
if (#ids ~= 0) then
    -- Submit the task id to the head of the push list WAITING to make it pop up first
    redis.call('lPush',KEYS[2],unpack(ids))
    -- Remove the corresponding id from the delay set DELAYED
    redis.call('zRem',KEYS[1],unpack(ids))
end  
return ids
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::RESERVE),
            $this->getKey(self::WAITING),
            microtime(true),
            $number
        ], 2);
    }

    /**
     * Move the timeout retry task to the waiting queue
     * @param int $number
     * @return mixed
     * @throws \RedisException
     */
    public function moveExpiredRetry($number = 50)
    {
        $script = <<<SCRIPT
-- Pop up number tasks from the redistribution set RETRY
local ids = redis.call('zRangeByScore', KEYS[1], 1 , ARGV[1], 'LIMIT' , 0 ,ARGV[2])
if (#ids ~= 0) then
    -- Submit the task id to the end of the push list WAITING
    redis.call('rPush',KEYS[2], unpack(ids))
    -- Remove the corresponding id from the delay set DELAYED
    redis.call('zRem',KEYS[1], unpack(ids))
end  
return ids
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::RETRY),
            $this->getKey(self::WAITING),
            microtime(true),
            $number
        ], 2);
    }


    /**
     * Get execution timeout id
     * @return mixed
     * @throws \RedisException
     */
    public function popTimeoutJob()
    {
        $script = <<<SCRIPT
-- Pop up number tasks from the WORKING collection in execution
local ids = redis.call('zRangeByScore', KEYS[1], 1 , ARGV[1], 'LIMIT' , 0 ,1)
if (#ids ~= 0) then
    -- Set a new timeout allocation time
    redis.call('zAdd',KEYS[1], ARGV[2], ids[1])
    return ids[1]
end  
return false
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::WORKING),
            microtime(true),
            microtime(true) + Queue::WORKER_POP_TIMEOUT
        ], 1);
    }


    /**
     * Start to execute the task, remove the task in the waiting queue and set the execution information
     * @param $id
     * @return mixed
     * @throws \RedisException
     */
    public function reReserve($id)
    {
        $script = <<<SCRIPT
--Remove tasks from the delayed collection RESERVE
local number = redis.call('zRem', KEYS[1], ARGV[1])
if (number > 0) then
    -- Get the details of the current task
    local info_array = redis.call('HGetAll',KEYS[3]..ARGV[1])
    number = #info_array
    local info = {}
    for i=1,number,2 do
	    info[info_array[i]] = info_array[i+1]
    end
    info['exec_number'] = info['exec_number']+1
    -- Set the currently executing program, start execution time and status as executing
    redis.call('hMSet',KEYS[3]..ARGV[1], 'worker_id', ARGV[2], 'start_time', ARGV[3],'exec_number',info['exec_number'])
    -- Add task to execution set WORKING retry timestamp
    if (tonumber(info['timeout']) > 0) then
        redis.call('zAdd',KEYS[2],ARGV[3]+info['timeout'],ARGV[1])
    else
        redis.call('zAdd',KEYS[2],0,ARGV[1])
    end
    return cjson.encode(info)
end  
return false
SCRIPT;
        $info = $this->eval($script, [
            $this->getKey(self::RESERVE),
            $this->getKey(self::WORKING),
            $this->getKey(self::INFO),
            $id,
            BasicsConfig::name() . ':' . getmypid(),
            microtime(true),
        ], 3);
        $info && $info = json_decode($info, true);
        return $info;
    }

    /**
     * Delete successfully executed tasks
     * @param $id
     * @return mixed
     * @throws \RedisException
     */
    public function remove($id)
    {
        $script = <<<SCRIPT
--Remove task from execution set WORKING
local number = redis.call('zRem', KEYS[1], ARGV[1])
if (number > 0) then
--Delete task details
redis.call('del', KEYS[2]..ARGV[1])
--Add 1 to the completed quantity
redis.call('incr',KEYS[3])
end
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::WORKING),
            $this->getKey(self::INFO),
            $this->getKey(self::TASK_OVER),
            $id,
        ], 3);
    }


    /**
     * Start consumption to execute overtime tasks and return corresponding task details
     * @param $id
     * @return mixed
     * @throws \RedisException
     */
    public function consumeTimeoutWorking($id)
    {
        $script = <<<SCRIPT
--Get tasks from the execution set WORKING
local score = redis.call('zScore', KEYS[1], ARGV[1])
if (score ~= -1) then
    --Set the timeout time to -1 to indicate that it has been received by the worker process to execute timeout
    redis.call('zAdd', KEYS[1], -1, ARGV[1])
    -- Get the details of the current task
    local info_array = redis.call('HGetAll',KEYS[2]..ARGV[1])
    if (info_array) then
        local number = #info_array
        local info = {}
        for i=1,number,2 do
            info[info_array[i]] = info_array[i+1]
        end
        return cjson.encode(info)
    end
end  
return false
SCRIPT;
        $info = $this->eval($script, [
            $this->getKey(self::WORKING),
            $this->getKey(self::INFO),
            $id,
        ], 2);
        $info && $info = json_decode($info, true);
        return $info;
    }


    /**
     * Add error information to the details
     * @param int $id
     * @param string $error
     * @return mixed|string
     * @throws \RedisException
     */
    public function setErrorInfo(int $id, string $error)
    {
        $script = <<<SCRIPT
--Get error information from the detail hash
local info = redis.call('hGet', KEYS[1], 'error_info')
--Add error information to the detail hash
if (info ~= nil) then
redis.call('hSet', KEYS[1], 'error_info', info..ARGV[1])
return true
end
return false
SCRIPT;
        $res = $this->eval($script, [
            $this->getKey(self::INFO) . $id,
            $error,
        ], 1);
        return $res;
    }

    /**
     * Republish the failed task again
     * @param int $id
     * @param int $delay Retry delay time (s supports decimal precision to 0.001)
     * @return mixed|string
     * @throws \RedisException
     */
    public function retry(int $id, int $delay = 0)
    {
        $script = <<<SCRIPT
--Delete from the WORKING collection in execution
local number = redis.call('zRem', KEYS[1], ARGV[1])
--Add to the retry delay execution RETRY collection
if (number > 0) then
redis.call('zAdd', KEYS[2], ARGV[2], ARGV[1])
return true
end
return false
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::WORKING),
            $this->getKey(self::RETRY),
            $id,
            microtime(true)+$delay,
        ], 2);
    }


    /**
     * Failed task record
     * @param int $id
     * @param string $info
     * @return mixed|void
     */
    public function failed(int $id, string $info)
    {
        $script = <<<SCRIPT
local info = redis.call('HGetAll',KEYS[1]..ARGV[1])
if (#info > 0) then
--Detailed information is transferred to the failed record FAILED table
redis.call('hSet', KEYS[2],ARGV[1],ARGV[2])
    --Remove tasks from the execution set WORKING
    redis.call('zRem', KEYS[3], ARGV[1])
    --Delete details
    redis.call('del', KEYS[1]..ARGV[1])
end
SCRIPT;
        return $this->eval($script, [
            $this->getKey(self::INFO),
            $this->getKey(self::FAILED),
            $this->getKey(self::WORKING),
            $id,
            $info
        ], 3,);
    }

    /**
     * Set the execution timeout time of the task in execution
     * @param $id
     * @param int $timeout (s supports decimal precision to 0.001)
     * @return int Number of values ​​added
     */
    public function setWorkingTimeout($id, $timeout = 0): int
    {
        $this->getConnect()->zAdd($this->getKey(self::WORKING), ['XX'], $timeout ? (microtime(true) + $timeout) : 0, $id);
        return true;
    }

    /**
     * Delete failed task information
     * @param $id
     * @return bool|int 0 delete failed 1 succeed
     */
    public function removeFailedJob($id): int
    {
        return (int)$this->getConnect()->hDel($this->getKey(self::FAILED), $id);
    }


    /**
     * Get the number of tasks
     * @return int
     */
    public function getCount($type = 'all'): int
    {
        switch ($type) {
            case 'all'://all
                return (int)$this->getConnect()->get($this->getKey(self::TASK_NUMBER));
            case'waiting'://waiting for execution, including delivered but not allocated, allocated but not executed, delayed delivery, failed retry
                return $this->getConnect()->llen($this->getKey(self::WAITING))
                + $this->getConnect()->zCard($this->getKey(self::RESERVE))
                + $this->getConnect()->zCard($this->getKey(self::DELAYED))
                + $this->getConnect()->zCard($this->getKey(self::RETRY));
            case 'working'://executing
                return $this->getConnect()->zCard($this->getKey(self::WORKING));
            case 'failed'://failed queue
                return (int)$this->getConnect()->hLen($this->getKey(self::FAILED));
            case 'over'://completed
                return (int)$this->getConnect()->get($this->getKey(self::TASK_OVER));

        }
    }

    /**
     * Obtaining a list of all failed tasks will block when there are too many failed tasks
     * @return array
     */
    public function failedList(): array
    {
        return $this->getConnect()->hGetAll($this->getKey(self::FAILED));
    }

    /**
     * Clear all information in the queue
     */
    public function clean()
    {
        $connect = $this->getConnect();
        while (false !== ($keys = $connect->scan($iterator, $this->getKey(self::INFO) . '*', 50))) {
            $keys && $connect->del($keys);
        }
        return $connect->del($this->getKey(self::TASK_NUMBER),
            $this->getKey(self::TASK_OVER),
            $this->getKey(self::DELAYED),
            $this->getKey(self::WAITING),
            $this->getKey(self::RESERVE),
            $this->getKey(self::WORKING),
            $this->getKey(self::RETRY),
            $this->getKey(self::FAILED)
        );
    }

    /**
     * Get the actual key in redis
     * @param $key
     * @param null $queue
     * @return string
     */
    protected function getKey($key)
    {
        return $this->config['prefix'] . '{' . $this->queue . '}:' . $key;
    }

    /**
     * Get the sha1 hash value of the script
     * @param $script
     * @return mixed
     */
    protected function getScriptHash($script)
    {
        $scriptKey = md5($script);
        if (!array_key_exists($scriptKey, [])) {
            if (!$this->scriptHash[$scriptKey] = $this->getConnect()->script('load', $script)) {
                throw new \RedisException($this->getConnect()->getLastError());
            }
        }
        return $this->scriptHash[$scriptKey];
    }

    /**
     * Execute lua script
     * @param $script
     * @param array $args
     * @param int $num_keys
     * @throws \RedisException
     */
    protected function eval($script, $args = [], $num_keys = 0)
    {
        $redis = $this->getConnect();
        $scriptHash = $this->getScriptHash($script);
        $result = $redis->evalSha($scriptHash, $args, $num_keys);
        if ($err = $redis->getLastError()) {
            $redis->clearLastError();
            throw new \RedisException($err);
            unset($this->scriptHash[$scriptHash]);
            $this->scriptError[$scriptHash . '-' . Coroutine::getCid()] = $err;
            return $this->eval($script, $args, $num_keys);
        }
        unset($this->scriptError[$scriptHash . '-' . Coroutine::getCid()]);
        return $result;
    }
}
