<?php

namespace MultilineQM\Config;

use MultilineQM\Library\Traits\Config;

/**
 * Class QueueConfig
 * @method static name() Get the name of the configuration object of the current queue
 * @method static worker_number() Get the number of worker processes of the configuration object of the current queue
 * @method static memory_limit() Get the memory limit of the configuration object of the current queue (mb)
 * @method static sleep_seconds() Get the interval waiting time of the configuration object of the current queue
 * @method static timeout() Get the task timeout time of the current queue in seconds
 * @method static fail_expire() The interval waiting time for obtaining the configuration object of the current queue in seconds
 * @method static fail_number() Get the maximum number of failures allowed in the current queue
 * @method static timeout_handle() Get the callable that needs to be executed after the timeout
 * @method static fail_handle() The fail_handle that needs to be executed after getting the failure
 * @method static worker_start_handle() Execute the function after the worker process of the current queue is started
 * @package MultilineQM\Config
 */
class QueueConfig implements ConfigInterface
{
    use Config;

    protected static $queues = [];
    protected $name;
    protected $worker_number;
    protected $memory_limit;
    protected $sleep_seconds;
    protected $timeout;
    protected $fail_number;
    protected $fail_expire;
    protected $timeout_handle;
    protected $fail_handle;
    protected $worker_start_handle;

    public function __construct($name, $worker_number, $memory_limit, $sleep_seconds,$timeout,$fail_number,$fail_expire,$timeout_handle,$fail_handle,$worker_start_handle)
    {
        $this->name = $name;
        $this->worker_number = $worker_number;
        $this->memory_limit = $memory_limit;
        $this->sleep_seconds = $sleep_seconds;
        $this->timeout = $timeout;
        $this->fail_number = $fail_number;
        $this->fail_expire = $fail_expire;
        $this->timeout_handle = $timeout_handle;
        $this->fail_handle = $fail_handle;
        $this->worker_start_handle = $worker_start_handle;

    }

    public static function set($queues)
    {
        self::checkSet();
        foreach ($queues as $value) {
            self::$queues[$value['name']] = new self(
                $value['name'],
                $value['worker_number'],
                $value['memory_limit']*1024*1024,
                $value['sleep_seconds'],
                $value['timeout'],
                $value['fail_number'],
                $value['fail_expire'],
                $value['timeout_handle'],
                $value['fail_handle'],
                $value['worker_start_handle']
            );
        }
    }

    /**
     * Get the array of queue configuration objects
     * @return QueueConfig[]
     */
    public static function queues(){
        return self::$queues;
    }

    /**
     * Get the configuration information of a specific queue
     * @param null $queue
     * @return self()
     */
    public static function queue($queue = null)
    {
        $queue = $queue?:ProcessConfig::queue();
        return self::$queues[$queue];
    }

    /**
     * @param $name
     * @param $arg
     * @return mixed
     */
    public static function __callStatic($name, $arg)
    {
        return self::$queues[ProcessConfig::queue()]->{$name}();
    }


}