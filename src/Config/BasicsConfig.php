<?php


namespace MultilineQM\Config;

use MultilineQM\Queue\Driver\DriverInterface;
use MultilineQM\Library\Traits\Config;

/**
 * @method static name() Get the current program ID
 * @method static pid_path() Get the storage path of the management process pid
 * @method static \MultilineQM\Queue\Driver\DriverInterface driver() Get the queue driver
 * @method static worker_start_handle() worker execute function after process starts
 * Class BasicsConfig
 * @package MultilineQM\Config
 */
class BasicsConfig implements ConfigInterface
{
    use Config;

    private static $name;
    private static $pid_path = '/temp';
    private static $driver;
    private static $worker_start_handle;

    public static function set($name, $pid_path,DriverInterface $driver,$worker_start_handle)
    {
        self::checkSet();
        self::$name = $name;
        if (MULTILINE_QUEUE_CLI && !is_dir($pid_path) && !is_writable($pid_path)) {
            throw new \Exception('pid_path Must be a correct readable and writable path');
        }
        self::$pid_path = rtrim($pid_path,'/');
        self::$driver = $driver;
        self::$worker_start_handle = $worker_start_handle;
    }

    /**
     * Get the pid storage file address of the management process
     * @return string
     */
    public static function pid_file(): string
    {
        return self::$pid_path . '/MultilineQM.pid';
    }
}