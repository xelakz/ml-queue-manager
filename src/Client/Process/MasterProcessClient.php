<?php

namespace MultilineQM\Client\Process;

use MultilineQM\Process\MasterProcess;

/**
 * master message process client (master process and management process communication client)
 * Class MasterClient
 * @package MultilineQM\Socket
 */
class MasterProcessClient extends Client
{
    public function __construct(\Swoole\Coroutine\Socket $socket, MasterProcess $process)
    {
        parent::__construct($socket);
        $this->process = $process;
    }


}