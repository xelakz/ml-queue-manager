<?php

namespace MultilineQM\Log\Driver;


use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use MultilineQM\Config\LogConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Serialize\JsonSerialize;

class RotatingFileLogDriver implements LogDriverInterface
{

    /**
     * Detailed debug information
     */
    public const DEBUG = Logger::DEBUG;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = Logger::INFO;

    /**
     * Uncommon events
     */
    public const NOTICE = Logger::NOTICE;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = Logger::WARNING;

    /**
     * Runtime errors
     */
    public const ERROR = Logger::ERROR;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public const CRITICAL = Logger::CRITICAL;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = Logger::ALERT;

    /**
     * Urgent alert.
     */
    public const EMERGENCY = Logger::EMERGENCY;


    protected static $logger;


    /**
     * Get the current log instance
     * @return mixed
     */
    protected function getLogger(): Logger
    {
        if (!static::$logger) {
            !static::$logger = new Logger('log');
            static::$logger->pushHandler(new RotatingFileHandler(LogConfig::path().'/MultilineQM.log', 30, LogConfig::level()));
        }
        return static::$logger;
    }

    /**
     * Add a log record at the DEBUG level.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function debug($message, array $context = [])
    {
        return $this->addRecord(self::DEBUG, $message. "\n", $context);
    }

    /**
     * Add INFO level logging.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function info($message, array $context = [])
    {
        return $this->addRecord(self::INFO, $message. "\n", $context);
    }

    /**
     * Added notification level logging l.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function notice($message, array $context = [])
    {
        return $this->addRecord(self::NOTICE, $message. "\n", $context);
    }

    /**
     * Added warning level logging.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function warning($message, array $context = [])
    {
        return $this->addRecord(self::WARNING, $message . "\n", $context);
    }

    /**
     * Add error level logging
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function error($message, array $context = [])
    {
        return $this->addRecord(self::ERROR, $message. "\n", $context);
    }

    /**
     * Add critical level logging.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function critical($message, array $context = [])
    {
        return $this->addRecord(self::CRITICAL, $message. "\n", $context);
    }

    /**
     * Add ALERT level logging.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function alert($message, array $context = [])
    {
        return $this->addRecord(self::ALERT, $message. "\n", $context);
    }

    /**
     * Add emergency level logging.
     *
     * @param string $message log information
     * @param mixed[] $context log context array
     */
    public function emergency($message, array $context = [])
    {
        return $this->addRecord(self::EMERGENCY, $message. "\n", $context);
    }

    /**
     * Logging (channel cache will be reset)
     * @param int $level log level
     * @param string $message log information
     * @param array $context log context array
     */
    protected function addRecord(int $level, string $message, array $context = [])
    {
        $result = self::getLogger()->addRecord($level, $message, $context);
        self::getLogger()->reset();
        return $result;
    }

}