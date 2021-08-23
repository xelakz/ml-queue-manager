<?php


namespace MultilineQM\Log;

use MultilineQM\Config\LogConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Log\Driver\LogDriverInterface;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Serialize\JsonSerialize;

/**
 * Log type
 * @method static debug(string $message, array $context = []); debugging information
 * @method static info(string $message, array $context = []); information
 * @method static notice(string $message, array $context = []); notice
 * @method static warning(string $message, array $context = []); warning
 * @method static error(string $message, array $context = []); general error
 * @method static critical(string $message, array $context = []); dangerous error
 * @method static alert(string $message, array $context = []); alert error
 * @method static emergency(string $message, array $context = []); emergency error
 *
 * @see LogDriverInterface
 * @package MultilineQM\Log
 */
class Log
{
    private static $driver;

    private static $levelOut=[
        'debug'=>'info',
        'info'=>'normal',
        'notice'=>'warning',
        'warning'=>'warning',
        'error'=>'error',
        'critical'=>'error',
        'alert'=>'error',
        'emergency'=>'error'
    ];

    /**
     * Get the log driver
     * @return LogDriverInterface
     */
    public static function getDriver(): LogDriverInterface
    {
        if(!self::$driver){
            self::$driver = LogConfig::driver();
        }
        return self::$driver;
    }

    public static function __callStatic($name, $arguments)
    {
        $arguments[0] ='['.ProcessConfig::queue().':'.ProcessConfig::getType().':'.getmypid().']'.$arguments[0];
        if(call_user_func_array([self::getDriver(),$name],$arguments) && !ProcessConfig::daemon()){
            //If the record is successful and not running in the background, the corresponding information will be printed to the screen
            OutPut::{self::$levelOut[$name]}(
                (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v')
                ."【{$name}】".$arguments[0]
                .(!empty($arguments[1])?JsonSerialize::serialize($arguments[1]):'')
                ."\n");
        }
    }

}