<?php

namespace MultilineQM\Client\Process;

use MultilineQM\Client\ClientInterface;
use MultilineQM\Process\WorkerProcess;

/**
 * Worker message client (a client for communication between worker and management process)
 * Class WorkerClient
 * @package MultilineQM\Socket
 */
class WorkerClient extends Client
{

    public function __construct(\Swoole\Coroutine\Socket $socket, WorkerProcess $process)
    {
        parent::__construct($socket);
        $this->process = $process;
    }


}