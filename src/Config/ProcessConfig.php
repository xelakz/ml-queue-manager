<?php

namespace MultilineQM\Config;

use MultilineQM\Library\Traits\Config;

/**
 * Class ProcessConfig
 * @method static string unixSocketPath() Get the unixSocket directory
 * @method static queue() Get the name of the current process processing queue
 * @method static daemon() Get whether the current process is running in daemon mode
 * @package MultilineQM\Config
 */
class ProcessConfig implements ConfigInterface
{
    use Config;

    const STATUS_ERROR = -1;//abnormal
    const STATUS_IDLE = 0;//idle
    const STATUS_BUSY = 1;//busy

    const SIG_STOP = SIGTERM;//Stop
    const SIG_STATUS = SIGUSR1;//Get process status
    const SIG_RELOAD = SIGUSR2;//Graceful restart

    private static $type='manage';
    private static $unixSocketPath = '/tmp';
    private static $queue = null;
    private static $daemon = false;

    public static function set($unixSocketPath){
        self::checkSet();
        self::$unixSocketPath = rtrim($unixSocketPath,'/').'/'.'MultilineQM_'.BasicsConfig::name().'_master';
        !file_exists(self::$unixSocketPath) && mkdir(self::$unixSocketPath,0744,true);
    }

    /**
     * Set the current process type to manage
     */
    public static function setManage(){
        self::$type = 'manage';
    }

    /**
     * Set the current process type to master
     */
    public static function setMaster(){
        self::$type = 'master';
    }

    /**
     * Set the current process type to worker
     */
    public static function setWorker(){
        self::$type = 'worker';
    }

    /**
     * Get process type
     * @return string
     */
    public static function getType(){
        return self::$type;
    }

    /**
     * Set the queue to which the current process belongs
     * @param $queue
     * @throws \MultilineQM\Exception\ConfigException
     */
    public static function setQueue($queue){
        self::checkSet('queue');
        self::$queue = $queue;
    }

    /**
     * Set the running state of the process to background mode
     */
    public static function setDaemon(){
        self::$daemon = true;
    }

    public static function getStatusLang($status,$lan = 'en'){
        switch ($status){
            case self::STATUS_ERROR:
                return 'error';
            case self::STATUS_IDLE:
                return 'idle';
            case self::STATUS_BUSY:
                return 'busy';
        }
        return 'error status';
    }

}