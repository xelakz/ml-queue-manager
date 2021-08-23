<?php

namespace MultilineQM\Config;

use MultilineQM\Library\Traits\Config;
use MultilineQM\Log\Driver\LogDriverInterface;

/**
 * Class LogConfig
 * @method static path() Get file address
 * @method static level() Get level
 * @method static LogDriverInterface driver() Get the log driver
 * @package MultilineQM\Config
 */
class LogConfig implements ConfigInterface
{
    use Config;

    private static $path;
    private static $level;
    private static $driver;

    public static function set($path, $level, $driver)
    {
        self::checkSet();
        if (MULTILINE_QUEUE_CLI && !is_dir($path) && !is_writable($path)) {
            throw new \Exception('log path Must be a correct readable and writable path');
        }
        if(is_string($driver) && class_exists($driver)){
            $driver = new $driver;
        }
        if(!$driver instanceof LogDriverInterface){
            throw new \Exception('log dirver must be MultilineQM\Log\Driver\LogDriverInterface realization of');
        }
        self::$path = rtrim($path,'/').'/'.BasicsConfig::name();
        self::$level = $level;
        self::$driver = $driver;
    }

}