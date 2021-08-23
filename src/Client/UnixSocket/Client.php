<?php

namespace MultilineQM\Client\UnixSocket;

use MultilineQM\Client\ClientInterface;
use MultilineQM\Exception\ClientException;
use Swoole\Coroutine\Socket;

abstract class Client implements ClientInterface
{
    use \MultilineQM\Library\Traits\Client;

    protected $socket;
    protected $process;
    protected $unixSocketPath;

    public function __construct(string $unixSocketPath)
    {
        $this->unixSocketPath = $unixSocketPath;
        $this->socket = new Socket(AF_UNIX, SOCK_STREAM);
        if (!$this->socket->connect($unixSocketPath)) {
            throw new ClientException('Connection failed', $this->socket->errCode);
        }
        $this->setProtocol();
    }

    public function getUnixSocketPath(){
        return $this->unixSocketPath;
    }
}