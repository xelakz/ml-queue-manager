<?php

namespace MultilineQM\Client\Process;

use MultilineQM\Process\ManageProcess;

/**
 * Management message process client (management process and master process and worker process communication client)
 * Class ManageClient
 * @package MultilineQM\Socket
 */
class ManageProcessClient extends Client
{
    public function __construct(\Swoole\Coroutine\Socket $socket, ManageProcess $process)
    {
        parent::__construct($socket);
        $this->process = $process;
    }


}