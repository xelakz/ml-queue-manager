<?php
return [
    'basics'=>[
        'name'=>'ml-queue-1',//When multiple servers start at the same time, you need to set names separately
        'pid_path' => null,//The main process pid storage path (need to be writable)
        'driver'=> new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
    ],
    'log'=>[
      'level'=>\MultilineQM\Log\Driver\RotatingFileLogDriver::INFO
    ],
    'queue' => [
        [
            'name' =>'test',//queue name
            'worker_number' => 4,//The number of worker processes in the current queue
            'memory_limit' => 1024, //Maximum used memory of the current queue work process, if exceeded, restart. Unit MB
            'sleep_seconds' => 0.5,//Monitoring process sleep time (seconds, the minimum allowed decimal is 0.001)
            'timeout' => 25,//Timeout period (this configuration value uses the configuration used by the delivery task process, not the queue execution process)
            'timeout_handle' => function(){
                var_dump('Timed out');
             },//The function is triggered after timeout
            'fail_handle' => function(){
                var_dump('Failed');
            },//Failed callback function
            'fail_number' => 3,//The maximum number of failures allowed (this configuration value uses the configuration used by the delivery task process, not the queue execution process)
            'fail_expire' => 3,//Failure retry delay time (seconds This configuration value uses the configuration used by the delivery task process, not the queue execution process)
        ],
//        [
//            'name' => 'test2',//Queue name
//            'worker_number' => 3,//The current number of queue worker processes
//            'memory_limit' => 1024, //Maximum used memory of the current queue work process, if exceeded, restart. Unit MB
//            'sleep_seconds' => 1,//Monitor the sleep time of the process (seconds, the minimum allowed decimal is 0.001)
//            'timeout'=>60,//overtime time
//            'timeout_handle'=>function(){
//                var_dump('Retry after timeout');
//            },//Trigger function after timeout
//            'fail_handle'=>function(){
//                var_dump('Failed');
//            },//Failure callback function
//            'fail_number'=>3,//Number of failed retries
//            'fail_expire'=>3,//Failure retry delay time (seconds)
//        ]
    ],
];