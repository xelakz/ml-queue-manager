<?php

namespace MultilineQM\Process;

use MultilineQM\Client\Process\ManageProcessClient;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Config\QueueConfig;
use MultilineQM\Library\Helper;
use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;

class ManageProcess
{

    private $queueProcess = [];
    private $stop = false;
    private $processes;
    private $daemon = false;


    public function __construct()
    {
        swoole_set_process_name('multilinequeue:manage');

    }

    /**
     * Get the storage file location of the status content
     * @return string
     */
    public function getStatusFile()
    {
        return BasicsConfig::pid_path().'/MultilineQM_status.log';
    }


    /**
     * Get the pid of the running queue manage process
     */
    public function getPid()
    {
        if (file_exists(BasicsConfig::pid_file())) {
            $pid = file_get_contents(BasicsConfig::pid_file());
            if ($pid && Process::kill($pid, 0)) {
                return $pid;
            }
        }
        return false;
    }


    /**
     * Run
     * @throws \Exception
     */
    public function run()
    {
        //Close the coroutine
        ini_set('swoole.enable_coroutine', false);
        //Set signal asynchronous
        pcntl_async_signals(true);
        //Set the current process type
        ProcessConfig::setManage();
        //Registration error listener
        $this->registerHandle();
        //Write pid
        if ($this->getPid()) {
            Log::error('Started and cannot be restarted');
            exit();
        }
        file_put_contents(BasicsConfig::pid_file(), getmypid());
        //Start signal monitoring
        $this->registerSignal();
        //Create the master process
        foreach (QueueConfig::queues() as $queue) {
            $this->queueProcess[$queue->name()] = [
                'need_master' => 1,
                'need_worker' => $queue->worker_number(),
                'master_number' => 0,
                'worker_number' => 0,
                'worker' => [],
                'master' => 0
            ];
            $this->createMasterProcess($queue->name());
        }
        $this->overMonitor();
    }


    /**
     * Stop the current process and its child processes
     */
    public function stop()
    {
        if (empty($this->processes)) {
            exit();
        }
        $this->stop = true;
        foreach ($this->queueProcess as $queueProcess) {
            if ($queueProcess['master']) {
                if (@!Process::kill($queueProcess['master'], SIGKILL)) {
                    unset($this->processes[$queueProcess['master']]);
                }
                foreach ($queueProcess['worker'] as $pid) {
                    if (@!Process::kill($pid, SIGKILL)) {
                        unset($this->processes[$pid]);
                    }
                }
            }
        }
        foreach ($this->processes as $pid => $value) {
            if (@!Process::kill($pid, SIGKILL)) {
                unset($this->processes[$pid]);
            }
        }
    }


    /**
     * Safely restart the WORKER child process of the current process
     */
    public function reload()
    {
        Log::info("Restart the worker process smoothly");
        foreach ($this->queueProcess as $queueProcess) {
            //Safely exit the worker process
            foreach ($queueProcess['worker'] as $pid) {
                if (@!Process::kill($pid, ProcessConfig::SIG_RELOAD)) {
                    unset($this->processes[$pid]);
                }
            }
        }
    }

    /**
     * Register the listener function
     */
    protected function registerHandle()
    {
        //Registration error capture capture warning information
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline, array $errcontext) {
            switch ($errno){
                case E_NOTICE:
                    Log::notice($errno.':'. $errstr. 'In'. $errfile. $errline, $errcontext);
                    break;
                case E_WARNING:
                    Log::warning($errno.':'. $errstr. 'In'. $errfile. $errline, $errcontext);
                    break;
                case E_STRICT:
                    Log::debug('e_script:'.$errno.':'. $errstr. 'In'. $errfile. $errline, $errcontext);
                    break;
            }
            return true;
        }, E_NOTICE | E_WARNING | E_STRICT);
        //Register the global interrupt function
        register_shutdown_function(function () {
            $error = error_get_last();
            if (ProcessConfig::getType() =='manage') {
                getmypid() == $this->getPid() && @unlink(BasicsConfig::pid_file());
            }
            if ($error) {
                Log::emergency($error['type'].':'. $error['message']);
                exit($error['type']);
            }
            exit();
        });
    }

    /**
     * Register signal monitor
     */
    protected function registerSignal()
    {
        //The exit of the child process
        Process::signal(SIGCHLD, function ($sig) {
            while ($ret = Process::wait(false)) {
                if (isset($this->processes[$ret['pid']])) {
                    $this->processes[$ret['pid']]['status'] = 2;
                    Log::info($this->processes[$ret['pid']]['type']. "Child process: {$ret['pid']} Exit: {$sig}");
                    switch ($this->processes[$ret['pid']]['type']) {
                        case 'worker':
                            $queue = $this->processes[$ret['pid']]['queue'];
                            unset($this->queueProcess[$queue]['worker'][$ret['pid']]);
                            unset($this->processes[$ret['pid']]);
                            $this->queueProcess[$queue]['worker_number']--;
                            if ($this->stop) {
                                empty($this->processes) && exit(0);
                            } else {
                                //Notify the matser process to release the corresponding worker process
                                $this->processes[$this->queueProcess[$queue]['master']]['socket']->send('unsetWorker', ['workerPid' => $ret['pid']]);
                                Log::info("Restart the worker child process");
                                $this->createWorkerProcess($queue, 1);
                            }
                            break;
                        case'master':
                            $queue = $this->processes[$ret['pid']]['queue'];
                            $this->queueProcess[$queue]['master'] = 0;
                            @unlink($this->processes[$ret['pid']]['unixSocketPath']);
                            unset($this->processes[$ret['pid']]);
                            $this->queueProcess[$queue]['master_number']--;
                            if ($this->stop) {
                                empty($this->processes) && exit(0);
                            } else {
                                //Notify the worker process to release the master process
                                foreach ($this->queueProcess[$queue]['worker'] as $pid) {
                                    $this->processes[$pid]['socket']->send('unSetMaster', ['masterPid' => $ret['pid']]);
                                }
                                Log::info("Restart the master child process");
                                $this->createMasterProcess($queue);
                            }
                    }
                }
            }
        });
        //Stop
        Process::signal(ProcessConfig::SIG_STOP, function () {
            $this->stop();
        });
        //Restart the worker process smoothly
        Process::signal(ProcessConfig::SIG_RELOAD, function () {
            $this->reload();
        });
        //Status query signal
        Process::signal(ProcessConfig::SIG_STATUS, function () {
            foreach ($this->processes as $pid => $value) {
                !Process::kill($pid, 0) && $this->processes[$pid]['status'] = ProcessConfig::STATUS_ERROR;
            }
            $this->outputStatus();
        });
        //Interrupt signal
        Process::signal(SIGINT, function () {
            if (ProcessConfig::getType() =='manage') {
                $this->stop();
            }
        });
    }

    /**
     * Create the master listener process of the queue
     * @param string $queue queue name
     * @return array
     * @throws \Exception
     */
    protected function createMasterProcess(string $queue)
    {
        $process = new Process(function ($process) use ($queue) {
            (new MasterProcess($process, $queue))->start();
        }, false, SOCK_STREAM, true);
        $pid = $process->start();
        if (!$pid) {
            throw new \Exception('Unable to create child process');
        }
        $processInfo = $this->listenMessage(['status' => ProcessConfig::STATUS_ERROR,'process' => $process,'queue' => $queue,'type' =>'master','startTime' => null]);
        $this->processes[$pid] = $processInfo;//It is running
        $this->queueProcess[$queue]['master'] = $pid;
        return $pid;
    }

    /**
     * The consumer worker process that creates the queue
     * @param string $queue queue name
     * @param int $number
     * @return array
     * @throws \Exception
     */
    protected function createWorkerProcess(string $queue, int $number = 1)
    {
        $pids = [];
        $masterPid = $this->queueProcess[$queue]['master'];
        for ($i = 0; $i <$number; $i++) {
            $process = new Process(function ($process) use ($queue) {
                (new WorkerProcess($process, $queue))->start();
            }, false, SOCK_STREAM, true);
            $pid = $process->start();
            if (!$pid) {
                throw new \Exception('Unable to create child process');
            }
            //Monitor worker process messages
            $processInfo = $this->listenMessage(['status' => ProcessConfig::STATUS_ERROR,'process' => $process,'queue' => $queue,'type' =>'worker','startTime' => null]);
            $this->processes[$pid] = $processInfo;
            $this->queueProcess[$queue]['worker'][$pid] = $pid;
            $pids[] = $pid;
            //Broadcast the unixSocketPath of the master process to the worker process
            $this->processes[$pid]['socket']->send('setMaster', ['masterPid' => $masterPid,'unixSocketPath' => $this->processes[$masterPid]['unixSocketPath'] ]);
        }
        return $pids;
    }


    /**
     * Listen for messages from the child process
     * @param $processInfo
     */
    protected function listenMessage($processInfo)
    {
        $processInfo['socket'] = new ManageProcessClient($processInfo['process']->exportSocket(), $this);
        \Swoole\Event::add($processInfo['process']->pipe, function () use ($processInfo) {
            $processInfo['socket']->recvAndExec();
        });
        return $processInfo;
    }


    /**
     * Monitor startup completion status
     */
    protected function overMonitor()
    {
        //Timed monitoring start is complete
        Timer::tick(500, function ($timer) {
            foreach ($this->queueProcess as $key => $value) {
                if ($value['master_number'] <$value['need_master'] || $value['worker_number'] <$value['need_worker']) {
                    return;
                }
                Timer::clear($timer);
                Log::info($key.' Startup successful');
                if ($this->daemon) {
                    OutPut::normal($key. " Started successfully...\n");
                }
            }
        });
    }


    /**
     * Output status information to file
     */
    public function outputStatus()
    {
        $processes = [];
        foreach ($this->queueProcess as $queue => $queueProcess) {
            if ($queueProcess['master']) {
                $processes[] = [
                    'queue' => $queue,
                    'status' => $this->processes[$queueProcess['master']]['status'],
                    'pid' => $queueProcess['master'],
                    'type' => 'master',
                    'startTime' => $this->processes[$queueProcess['master']]['startTime'],
                ];
                foreach ($queueProcess['worker'] as $pid) {
                    $processes[] = [
                        'queue' => $queue,
                        'status' => $this->processes[$pid]['status'],
                        'pid' => $pid,
                        'type' => 'worker',
                        'startTime' => $this->processes[$pid]['startTime'],
                    ];
                }
            }
        }
        file_put_contents($this->getStatusFile(), json_encode($processes));
    }

    /**
     * Formatted output status information
     * @param array $processes process status information
     */
    public function outPutStatusInfo(array $processes)
    {
        OutPut::normal("------queue------type---------pid--------status---------start time ---------Run time------\n");
        $time = time();
        foreach ($processes as $value) {
            OutPut::normal('   ');
            OutPut::normal($value['queue'], 10);
            OutPut::normal($value['type'], 10);
            OutPut::normal($value['pid'], 14);
            switch ($value['status']) {
                case ProcessConfig::STATUS_ERROR:
                    OutPut::error(ProcessConfig::getStatusLang($value['status']), 10);
                    break;
                case ProcessConfig::STATUS_BUSY:
                    OutPut::warning(ProcessConfig::getStatusLang($value['status']), 10);
                    break;
                case ProcessConfig::STATUS_IDLE:
                    OutPut::normal(ProcessConfig::getStatusLang($value['status']), 10);
                    break;
            }
            OutPut::normal($value['startTime'] ? date('ymd H:i:s', $value['startTime']) : '--', 20);
            OutPut::normal($value['startTime'] ? Helper::humanSeconds($time - $value['startTime']) : '--', 14);
            OutPut::normal("   " . PHP_EOL);
        }
    }


    /**
     * The master process is successfully established
     * @param $queue queue name
     * @param $unixSocketPath socket communication path
     * @param $pid master process id
     * @throws \Exception
     */
    public function masterOver($queue, $unixSocketPath, $pid)
    {
        Log::info("$queue:master process: {$pid} created");
        $this->queueProcess[$queue]['master_number']++;
        $this->processes[$pid]['unixSocketPath'] = $unixSocketPath;
        if ($this->queueProcess[$queue]['master'] == $pid) {
            //Let the old worker process add a new master
            foreach ($this->queueProcess[$queue]['worker'] as $workerPid) {
                $this->processes[$workerPid]['socket']->send('setMaster', ['masterPid' => $pid,'unixSocketPath' => $unixSocketPath]);
            }
            //Create a new worker process
            $this->createWorkerProcess($queue, $this->queueProcess[$queue]['need_worker']-count($this->queueProcess[$queue]['worker']));
        }
    }

    /**
     * The work process is successfully established
     * @param $queue
     * @param $workerPid
     */
    public function workerOver($queue, $workerPid)
    {
        $this->queueProcess[$queue]['worker_number']++;
        Log::info("$queue:worker process: {$workerPid} created");
    }

    /**
     * Set the working status of the process
     * @param $pid child process id
     * @param $status child process status
     * @param $startTime running time of the child process
     */
    public function setProcessStatus($pid, $status, $startTime)
    {
        if (isset($this->processes[$pid])) {
            $this->processes[$pid]['status'] = $status;
            $this->processes[$pid]['startTime'] = $startTime;
        }
    }


}