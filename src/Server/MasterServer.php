<?php

namespace MultilineQM\Server;


use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Process\MasterProcess;
use MultilineQM\Process\Message;

/**
 * The master process unixSocket server (used to monitor worker process messages)
 * Class MasterServer
 * @package MultilineQM\Server
 */
class MasterServer
{
    private $server;

    private $process;

    private $socketPath;

    public function __construct(MasterProcess $process)
    {
        $this->process = $process;
        $this->socketPath = ProcessConfig::unixSocketPath().'/'.$process->getPid().'.sock';
        $this->server = new \Swoole\Coroutine\Server('unix:'.$this->socketPath);
        $this->server->set(Message::protocolOptions());
    }

    public function getSocketPath(){
        return $this->socketPath;
    }

    /**
     * Send a message
     * @param $type
     * @param null $data
     * @param string $msg
     * @return mixed
     */
    public function send(string $type, $data = null, string $msg ='')
    {
        return $this->server->send((new Message($type, $data, $msg))->serialize());
    }

    /**
     * Set callback after connection
     * @param callable $callBack
     */
    public function handle(callable $callBack){
        $this->server->handle(function (\Swoole\Coroutine\Server\Connection $conn)use($callBack){
            call_user_func_array($callBack,[new MasterConnection($conn,$this->process)]);
        });
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->server, $name], $arguments);
    }


}