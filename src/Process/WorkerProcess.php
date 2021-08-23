<?php

namespace MultilineQM\Process;

use Co\WaitGroup;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Config\QueueConfig;
use MultilineQM\Exception\ClientException;
use MultilineQM\Job;
use MultilineQM\Log\Log;
use MultilineQM\Queue\Queue;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Process;
use Swoole\Timer;

/**
 * Don't define time-consuming blocking operations, so as to avoid the long-term failure of the coroutine control when the queue task is executed and the coroutine scheduling is generated.
 * Class WorkerProcess
 * @package MultilineQM\Process
 */
class WorkerProcess
{
    protected $pid;
    protected $client;
    protected $masterProcess = [];//master process
    protected $queue = null;
    protected $status = ProcessConfig::STATUS_IDLE; //Current process status
    protected $queueDriver = null;//queue driver
    protected $stop = false;//Queue configuration information
    protected $startTime = null;//start time

    /**
     * Worker process class
     * @param $process current worker process process
     * @param $queue queue name
     */
    public function __construct(Process $process, $queue)
    {
        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);//The current method allows to take effect after creation
        swoole_set_process_name("multilinequeue:$queue:w");
        ProcessConfig::setWorker();
        ProcessConfig::setQueue($queue);
        $this->queue = $queue;
        $this->pid = getmypid();
        $this->process = $process;
        $this->manageClient = new \MultilineQM\Client\Process\WorkerClient($process->exportSocket(), $this);
        $this->queueDriver = new Queue(BasicsConfig::driver(), $queue);

    }

    public function start()
    {
        Log::debug('The child process started to start');
        $this->startTime = time();
        //Register the child process signal to monitor the receiving coroutine scheduling, will be blocked by the synchronization code
        $this->registerSignal();
        if (is_callable(BasicsConfig::worker_start_handle())) {
            call_user_func(BasicsConfig::worker_start_handle());
        }
        if (is_callable(QueueConfig::worker_start_handle())) {
            call_user_func(QueueConfig::worker_start_handle());
        }
        //Block monitoring of manage process messages
        $this->listenManageMessage();
        $this->getStatus();
        Log::debug('Sub-process startup completed');
        //You must add a blocking program, otherwise the asynchronous signal monitoring will not take effect (the asynchronous signal monitoring of the coroutine waiting time will be blocked)
        while (true) {
            Coroutine::sleep(0.001);
        }


    }

    /**
     * Monitor management process messages
     */
    private function listenManageMessage()
    {
        go(function () {
            try {
                while (true) {
                    $this->manageClient->recvAndExec();
                }
            } catch (\Swoole\ExitException $e) {

            }
        });

    }

    /**
     * Listen to master process messages (will block the current program)
     * @param \MultilineQM\Client\UnixSocket\WorkerClient $client
     */
    private function listenMasterMessage(\MultilineQM\Client\UnixSocket\WorkerClient $client)
    {
        $wg = new WaitGroup();
        $wg->add();
        go(function () use ($client, $wg) {
            try {
                while (true) {
                    $client->recvAndExec();
                }
            } catch (ClientException $e) {
                $wg->done();
            } catch (\Swoole\ExitException $e) {

            }
        });
        $wg->wait();
        //Retry after 1s failed to get the message
        Coroutine::sleep(1);
        $this->connectMaster($client->getUnixSocketPath());

    }


    /**
     * Add master process record
     * @param $masterPid master process pid
     * @param $connection
     * @throws \Exception
     */
    public function setMaster($masterPid, $unixSocketPath): bool
    {
        //Skip already created
        if (isset($this->masterProcess['pid']) && $this->masterProcess['pid'] == $masterPid) {
            return false;
        }
        $this->masterProcess['pid'] = $masterPid;
        $this->masterProcess['unixSocketPath'] = $unixSocketPath;
        $this->masterProcess['connectNumber'] = 0;
        $this->connectMaster($unixSocketPath);
        Log::debug('master process:'. $masterPid.'Setup complete');
        return true;
    }

    /**
     * Connect to the master process server and listen for messages
     * @param $unixSocketPath
     * @return mixed
     */
    protected function connectMaster($unixSocketPath)
    {
        if (empty($this->masterProcess['unixSocketPath']) || $unixSocketPath != $this->masterProcess['unixSocketPath']) {
            return false;
        }
        if (empty($this->masterProcess['connectNumber'])) {
            Log::debug('Connect to master server', [$unixSocketPath]);
        } else {
            Log::warning('Disconnect and reconnect to master server', [$unixSocketPath]);
        }
        $this->masterProcess['client'] = null;
        $this->masterProcess['connectNumber']++;
        try {
            $this->masterProcess['client'] = new \MultilineQM\Client\UnixSocket\WorkerClient($this->masterProcess['unixSocketPath'], $this);
        } catch (ClientException $e) {
            //Retry after 1s connection failure
            return Timer::after(1000, function () use ($unixSocketPath) {
                $this->connectMaster($unixSocketPath);
            });
        }
        $this->masterProcess['client']->send('addWorker', ['status' => $this->status]);
        $this->listenMasterMessage($this->masterProcess['client']);
    }

    /**
     * Get the master process
     *
     * @return array
     * @throws \Exception
     */
    private function getMaster(): array
    {
        return $this->masterProcess;
    }

    /**
     * Remove master process information
     * @param $masterPid
     * @return bool
     * @throws \Exception
     */
    public function unSetMaster($masterPid)
    {
        if ($this->masterProcess['pid'] = $masterPid) {
            if ($this->masterProcess['client']) {
                $this->masterProcess['client']->close();
            }
            $this->masterProcess = [];
            return true;
        }
        return false;
    }


    /**
     * Register signal monitor
     */
    public function registerSignal()
    {
        //Monitor status query signal
        pcntl_signal(ProcessConfig::SIG_STATUS, function () {
            $this->getStatus();
        }, true);
        //Restart the worker process smoothly
        pcntl_signal(ProcessConfig::SIG_RELOAD, function () {
            $this->stop();
        }, true);
    }


    /**
     * Send a message to the master process
     * @param string $type message type
     * @param null|array $data message data array
     * @param string $msg message body
     * @return bool
     */
    public function sendToMaster(string $type, $data = null, string $msg ='')
    {
        $this->getMaster()['client']->send($type, $data, $msg);
        return true;
    }

    /**
     * Send information to the manage process
     * @param string $type
     * @param null $data
     * @param string $msg
     */
    public function sendToManage(string $type, $data = null, string $msg ='')
    {
        $this->manageClient->send($type, $data, $msg ='');
    }

    /**
     * Get the current process status (sent to the master and manage processes)
     */
    public function getStatus()
    {
        $this->sendToMaster('setWorkerStatus', ['status' => $this->status,'startTime' => $this->startTime]);
        $this->sendToManage('setProcessStatus', ['status' => $this->status,'startTime' => $this->startTime]);
    }

    /**
     * Consumer tasks
     * @param $id task id
     */
    public function consumeJob($id)
    {
        //If the current process is performing a task, refuse to perform a new task
        if ($this->status == ProcessConfig::STATUS_BUSY) {
            return false;
        }
        Log::debug('Start task', [$id]);
        $info = $this->queueDriver->consumeJob($id);
        if (!$info) {
            Log::debug('Corresponding details were not obtained', [$id]);
            $this->getStatus();
            return false;
        }
        $this->status = ProcessConfig::STATUS_BUSY;
        $this->getStatus();
        try {
            $failNumber = $info['fail_number'];
            $failExpire = $info['fail_expire'];
            $timeout = $info['timeout'];
            if ($timeout > 0) {
                $this->registerTimeSig();
                pcntl_alarm($timeout);
            }
        } catch (\Throwable $e) {
            Log::error($e);
            $this->status = ProcessConfig::STATUS_IDLE;
            $this->getStatus();
            return false;
        }
        try {
            $job = $info['job'];
            if (is_string($job) && class_exists($job)) {
                $job = new $job;
            }
            if ($job instanceof Job) {
                $job->handle();
            } else {
                call_user_func($info['job']);
            }
            $this->delTimeSig();
            $this->queueDriver->remove($id);
        } catch (\Throwable $e) {
            $this->delTimeSig();
            if ($this->queueDriver->setWorkingTimeout($id, -2)) {
                Log::error($e);
                $error = BasicsConfig::name() . ':' . getmypid() . ':' . $e->getCode() . ':' . $e->getMessage();
                try {
                    $handle_result = false;
                    if ($job instanceof Job) {
                        $handle_result = $job->fail_handle($info, $e);
                    }
                    if ($handle_result === false && QueueConfig::fail_handle()) {
                        call_user_func(QueueConfig::fail_handle(), $info, $e);
                    }
                } catch (\Throwable $exception) {
                    Log::error($exception);
                    $error .= "\nfail_handle:" . $exception->getCode() . ':' . $exception->getMessage();
                }
                if ($info['exec_number'] < $failNumber) {
                    $this->queueDriver->retry($id, $error, $failExpire);
                } else {
                    $this->queueDriver->failed($id, $info, $error);
                }
            }
        }
        $this->queueDriver->close();
        $this->status = ProcessConfig::STATUS_IDLE;
        if(QueueConfig::queue()->memory_limit() && memory_get_usage() > (QueueConfig::queue()->memory_limit())){
            Log::error('Excessive memory usage: '.memory_get_usage());
            $this->process->exit();
        }
        $this->getStatus();
    }

    /**
     * Overtime task processing
     * @param $id
     * @throws \RedisException
     */
    public function consumeTimeoutJob($id)
    {
        //If the current process is performing a task, refuse to perform a new task
        if ($this->status == ProcessConfig::STATUS_BUSY) {
            return false;
        }
        $this->status = ProcessConfig::STATUS_BUSY;
        if ($info = $this->queueDriver->consumeTimeoutJob($id)) {
            $error = 'Execution timeout';
            try {
                $job = $info['job'];
                if (is_string($job) && class_exists($job)) {
                    $job = new $job;
                }
                $handle_result = false;
                if ($job instanceof Job) {
                    $handle_result = $job->timeout_handle($info);
                }
                if ($handle_result === false && QueueConfig::timeout_handle()) {
                    call_user_func(QueueConfig::timeout_handle(), $info);
                }
            } catch (\Throwable $exception) {
                Log::error($exception);
                $error .= "\nfail_handle:" . $exception->getCode() . ':' . $exception->getMessage();
            }
            $this->queueDriver->failed($id, $info, $error);
        }
        $this->status = ProcessConfig::STATUS_IDLE;
        if(QueueConfig::queue()->memory_limit() && memory_get_usage() > (QueueConfig::queue()->memory_limit()*1024)){
            Log::error('Excessive memory usage: '.memory_get_usage());
            $this->process->exit();
        }
        $this->getStatus();
    }

    /**
     * Set the current clock signal
     */
    private function registerTimeSig()
    {
        pcntl_signal(SIGALRM, function () {
            $this->process->exit();
        });
    }

    /**
     * Cancel the clock signal
     */
    private function delTimeSig()
    {
        pcntl_signal(SIGALRM, SIG_IGN);
    }

    /**
     * End the current process
     * @param int $code end status code
     * @param bool $need_idle is it necessary to wait for the idle time to close
     * @param int $time timing time (milliseconds)
     */
    public function stop($code = 0, $need_idle = true, int $time = 500)
    {
        $this->unSetMaster($this->masterProcess['pid']);
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
}