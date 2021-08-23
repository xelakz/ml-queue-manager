<?php
namespace MultilineQM\Log\Driver;


interface LogDriverInterface
{

    /**
     * Add a log record at the DEBUG level.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function debug($message, array $context = []);

    /**
     * Add INFO level logging.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function info($message, array $context = []);

    /**
     * Added notification level logging l.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function notice($message, array $context = []);

    /**
     * Added warning level logging.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function warning($message, array $context = []);

    /**
     * Add error level logging
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function error($message, array $context = []);

    /**
     * Add critical level logging.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function critical($message, array $context = []);

    /**
     * Add ALERT level logging.
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     */
    public function alert($message, array $context = []);


    /**
     * Add emergency level logging.
     *
     * @param string  $message The log message
     * @param mixed[] $context The log context
     */
    public function emergency($message, array $context = []);


}