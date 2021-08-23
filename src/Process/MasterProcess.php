<?php

namespace MultilineQM\Process;

use Co\Channel;
use MultilineQM\Client\Process\MasterProcessClient;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Config\QueueConfig;
use MultilineQM\Exception\ClientException;
use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Queue\Queue;
use MultilineQM\Server\MasterConnection;
use MultilineQM\Server\MasterServer;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;

class MasterProcess
{
    protected $pid;//Current process id
    protected $manageClient;//Client that communicates with the management process
    protected $process;//Current process object
    protected $workProcess = [];//work process array
    protected $server = null;
    protected $queue = null;//queue name
    protected $queueDriver = null;
    protected $status = ProcessConfig::STATUS_IDLE;
    protected $workerChannel;
    protected $startTime = null;//start time

    public function __construct(Process $process, string $queue)
    {
        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);//The current method allows to take effect after creation
        swoole_set_process_name("multilinequeue:$queue:m");
        ProcessConfig::setMaster();
        ProcessConfig::setQueue($queue);
        $this->pid = getmypid();
        $this->process = $process;
        $this->queue = $queue;
        $this->manageClient = new MasterProcessClient($process->exportSocket(), $this);
        $this->queueDriver = new Queue(BasicsConfig::driver(),$queue);
        $this->status = ProcessConfig::STATUS_IDLE;
        $this->workerChannel = new Channel(QueueConfig::worker_number());
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function start()
    {
        Log::debug("Child process started");
        $this->startTime = time();
        //Register the child process signal monitor
        $this->registerSignal();
        //Monitor manage process messages
        go(function () {
            while (true) {
                $this->manageClient->recvAndExec();
            }
        });
        $this->startServer();
        $this->monitorQueue();
        $this->setOver();
        $this->getStatus();
        Log::debug("The child process started successfully");
        //A blocking program must be added, otherwise the asynchronous signal monitoring will not take effect (the asynchronous signal monitoring of the coroutine waiting time will be blocked)
        while (true) {
            Coroutine::sleep(0.001);
        }

    }


    /**
     * Start the service between the worker processes
     * @throws \Exception
     */
    protected function startServer()
    {
        $pid = 0;
        //Monitor worker process messages
        go(function () {
            try {
                $this->server = new MasterServer($this);
                $this->server->handle(function (MasterConnection $connection) {
                    try {
                        while (true) {
                            $connection->recvAndExec();
                        }
                    } catch (ClientException $e) {
                        Log::error($e, [$connection->pid]);
                        $this->unsetWorker($connection->pid);
                    }
                });
                if (!$this->server->start()) {
                    new \Exception('master service monitoring failed:'. $this->server->errCode);
                }
            } catch (\Throwable $e) {
                $this->exceptionHandler($e);
            }

        });
    }

    /**
     * Notify the manage process of initialization completion
     */
    protected function setOver()
    {
        //Notify that the manage process is set up
        go(function () {
            $this->manageClient->send('masterOver', ['queue' => $this->queue,'unixSocketPath' => $this->server->getSocketPath()]);
        });
    }

    /**
     * Listening queue
     * @throws \RedisException
     */
    protected function monitorQueue()
    {
        //The queue executes tasks regularly
        $this->queueDriver->timerInterval();
        $this->queueDriver->popInterval();
        //Processing queue tasks
        go(function () {
            while (true) {
                $pid = $this->workerChannel->pop();
                $idInfo = $this->queueDriver->pop();
                $this->status = ProcessConfig::STATUS_BUSY;
                if (!empty($this->workProcess[$pid])) {
                    Log::debug("Get the queue task", $idInfo);
                    $this->status = ProcessConfig::STATUS_BUSY;
                    //Distribute if the process exists
                    $this->setWorkerStatus(ProcessConfig::STATUS_BUSY, $pid);
                    $this->sendToWorker($pid,'consume'. $idInfo['type'], ['id' => $idInfo['id']]);
                    Log::debug('Distribute the queue task to the worker process:'. $pid, $idInfo);
                }
                $this->status = ProcessConfig::STATUS_IDLE;
            }
        });
    }


    /**
     * Register signal monitor
     */
    protected function registerSignal()
    {
        /**
         * Status query signal
         */
        pcntl_signal(ProcessConfig::SIG_STATUS, [$this,'getStatus']);
    }

    public function getStatus(){
        $this->sendToManage('setProcessStatus', ['status' => $this->status,'startTime'=>$this->startTime]);
    }

    /**
     * Send information to the manage process
     * @param string $type
     * @param null $data
     * @param string $msg
     */
    public function sendToManage(string $type, $data = null, string $msg ='')
    {
        $this->manageClient->send($type, $data, $msg);
    }

    /**
     * Add worker process record
     * @param $status
     * @param $pid
     * @param $connection
     * @throws \Exception
     */
    public function addWorker($status, $pid, $connection)
    {
        //Skip already created
        if (isset($this->workProcess[$pid])) {
            return false;
        }
        $connection->pid = $pid;
        $info['connection'] = $connection;
        $info['status'] ='';
        $this->workProcess[$pid] = $info;
        Log::debug('worker process:'. $pid.'Record successful');
        $this->setWorkerStatus($status, $pid);
        $this->sendToManage('workerOver', ['workerPid' => $pid,'queue' => ProcessConfig::queue()]);
        return true;
    }


    /**
     * Send messages to the worker process
     * @param int $pid worker process id 0 represents all
     * @param string $type message type
     * @param null|array $data message data array
     * @param string $msg message body
     * @return bool
     */
    public function sendToWorker($pid = 0, string $type, $data = null, string $msg ='')
    {
        if (!$pid) {
            foreach ($this->workProcess as $value) {
                $value['connection']->send($type, $data, $msg);
            }
        } else {
            $this->workProcess[$pid]['connection']->send($type, $data, $msg);
        }
        return true;
    }

    /**
     * Release the worker process
     * @param $pid
     * @return bool
     */
    public function unsetWorker($workerPid)
    {
        if (!isset($this->workProcess[$workerPid])) {
            return false;
        }
        isset($this->workProcess[$workerPid]['connection']) && $this->workProcess[$workerPid]['connection']->close();
        unset($this->workProcess[$workerPid]);
        return true;
    }


    /**
     * Set the status of the work process
     * @param $status
     * @param $pid
     */
    public function setWorkerStatus($status, $pid)
    {
        if ($this->workProcess[$pid]['status'] !== $status) {
            $this->workProcess[$pid]['status'] = $status;
            switch ($status) {
                case ProcessConfig::STATUS_IDLE:
                    $this->workerChannel->push($pid);
                    break;
            }
        }
    }

    /**
     * End the current process
     * @param int $code end status code
     * @param bool $need_idle is it necessary to wait for the idle time to close
     * @param int $time timing time (milliseconds)
     */
    public function stop($code = 0, $need_idle = true, int $time = 500)
    {
        if (!$need_idle || $this->status == ProcessConfig::STATUS_IDLE) {
            Log::debug('End process, end code:'. $code);
            $this->process->exit($code);
        }
        Timer::tick($time, function () use ($code) {
            if ($this->status == ProcessConfig::STATUS_IDLE) {
                Log::debug('End process, end code:'. $code);
                $this->process->exit($code);
            }
        });
    }

    /**
     * Smooth start
     * @throws \Exception
     */
    public function reload()
    {
        $pid = $this->getPid();
        if (!$pid) {
            throw new \Exception('The queue is not started without a smooth restart');
        }
        Process::kill($pid, ProcessConfig::SIG_RELOAD);
        OutPut::normal("Smooth restart signal sent successfully");
    }

    /**
     * Exception handling function
     */
    public function exceptionHandler(\Throwable $e)
    {
        //Exception monitoring
        Log::critical($e);
        $this->stop();
    }
}