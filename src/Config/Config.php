<?php


namespace MultilineQM\Config;


use MultilineQM\Exception\ConfigException;
use MultilineQM\Log\Driver\RotatingFileLogDriver;

class Config
{
    /**
     * The configuration array null means that the remaining types must be filled in as default values
     * @var array[]
     */
    private static $configStructure = [
        'basics' => [//Basic configuration
            'class' => BasicsConfig::class,
            'value' => [
                'name' => 'queue-1',//The name of the current queue service. When multiple services are started at the same time, the names need to be set separately
                'pid_path' => '/tmp',//Process id storage path
                'driver' => null,//Queue-driven
                'worker_start_handle' => '',//Worker process start loading function
            ],
        ],
        'log' => [//Log configuration
            'class' => LogConfig::class,
            'value' => [
                'path' => '/tmp',
                'level' => \Monolog\Logger::INFO,
                'driver' => RotatingFileLogDriver::class
            ],
        ],
        'kafkaproducer' => [//Kafka configuration
            'class' => MessagingProducerConfig::class,
            'value' => [
                'driver' => null,
            ],
        ],
        'queue' => [//Queue configuration
            'class' => QueueConfig::class,
            'value' => [
                [
                    'name' => null,
                    'worker_number' => 3,//Number of work processes
                    'memory_limit' => 128,//Maximum number of memory used by the worker process (unit: mb)
                    'sleep_seconds' => 1,//Monitor the sleep time of the process (seconds, the minimum allowed decimal is 0.001)
                    'timeout' => 120,//The timeout period (s) is subject to the delivery task party
                    'fail_number' => 1,//The maximum number of failures is subject to the delivery party
                    'fail_expire' => 1,//Failed delayed delivery time (s) The delivery task shall prevail
                    'timeout_handle' => '', //Task timeout trigger function
                    'fail_handle' => '', //Task failure trigger function
                    'worker_start_handle' => '',//Worker process start loading function
                ]
            ],
        ],
    ];

    /**
     * Set configuration items
     * @param array $config
     * @return false|mixed
     * @throws ConfigException
     */
    public static function set(array $config)
    {
        defined('MULTILINE_QUEUE_CLI') || define('MULTILINE_QUEUE_CLI',false);
        foreach (self::$configStructure as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = [];
            }
            forward_static_call_array([$value['class'], 'set'], self::getArguments($key, $config[$key]));
        }
    }

    /**
     * Get configuration setting parameter array
     * @param $key
     * @param $configs
     * @return array
     * @throws ConfigException
     */
    protected static function getArguments($key, $configs): array
    {
        $structure = self::$configStructure[$key]['value'];
        $arguments = [];
        if (isset($structure[0]) && count($structure) == 1) {
            $argument = [];
            foreach ($configs as $value) {
                foreach ($structure[0] as $k => $v) {
                    if (isset($value[$k])) {
                        $argument[$k] = $value[$k];
                    } elseif (!is_null($v)) {
                        $argument[$k] = $v;
                    } else {
                        throw new ConfigException('a.Missing configuration item' . $key . '.' . $k);
                    }
                }
                $arguments[0][] = $argument;
            }
        } else {
            foreach ($structure as $k => $v) {
                if (isset($configs[$k])) {
                    $arguments[] = $configs[$k];
                } elseif (!is_null($v)) {
                    $arguments[] = $v;
                } else {
                    throw new ConfigException('b.Missing configuration item' . $key . '.' . $k);
                }
            }
        }
        return $arguments;
    }

}