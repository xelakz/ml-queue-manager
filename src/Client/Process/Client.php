<?php

namespace MultilineQM\Client\Process;

use MultilineQM\Client\ClientInterface;

/**
 * Process self-built UNIXSocket client base class (based on the coroutine client must be used in the coroutine)
 * Class Client
 * @package MultilineQM\Socket
 */
abstract class Client implements ClientInterface
{
    use \MultilineQM\Library\Traits\Client;

    protected $socket = null;
    protected $process;

    public function __construct(\Swoole\Coroutine\Socket $socket)
    {
        $this->socket = $socket;
        $this->setProtocol();
    }

}