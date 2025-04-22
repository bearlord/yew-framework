<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Yew\Core\Server\Config\ProcessConfig;
use Yew\Core\Server\Server;

/**
 * Class ProcessManager
 * @package Yew\Core\Server\Process
 */
class ProcessManager
{
    /**
     * @var ProcessConfig[]
     */
    private $customProcessConfigs = [];

    /**
     * @var Process[]
     */
    private $processes = [];

    /**
     * @var Server
     */
    private $server;

    /**
     * @var Process
     */
    private $masterProcess;

    /**
     * @var Process
     */
    private $managerProcess;

    /**
     * Default process class
     * @var string
     */
    private $defaultProcessClass;

    /**
     * Process groups
     * @var array
     */
    private $groups = [];

    /**
     * ProcessManager constructor.
     * @param Server $server
     * @param string $processClass
     */
    public function __construct(Server $server, string $processClass)
    {
        $this->server = $server;
        $this->defaultProcessClass = $processClass;
    }

    /**
     * Get process from id
     *
     * @param int $processId
     * @return Process
     */
    public function getProcessFromId(int $processId): ?Process
    {
        if ($processId == MasterProcess::ID) {
            return $this->masterProcess;
        }
        if ($processId == ManagerProcess::ID) {
            return $this->managerProcess;
        }
        return $this->processes[$processId] ?? null;
    }

    /**
     * Get process from name
     *
     * @param string $processName
     * @return Process
     */
    public function getProcessFromName(string $processName): ?Process
    {
        foreach ($this->processes as $process) {
            if ($process->getProcessName() == $processName) {
                return $process;
            }
        }
        return null;
    }

    /**
     * Merge config
     */
    public function mergeConfig()
    {
        foreach ($this->customProcessConfigs as $processConfig) {
            $processConfig->merge();
        }
    }

    /**
     * Get customer process configs
     *
     * @return array
     * @throws \Yew\Core\Plugins\Config\ConfigException|\Exception
     */
    public function getCustomProcessConfigs(): array
    {
        $this->mergeConfig();
        $customProcessConfigs = [];
        $configs = Server::$instance->getConfigContext()->get(ProcessConfig::key, []);
        foreach ($configs as $key => $value) {
            $processConfig = new ProcessConfig();
            $processConfig->setName($key);
            $customProcessConfigs[$key] = $processConfig->buildFromConfig($value);
            if ($processConfig->getClassName() == null) {
                $processConfig->setClassName($this->defaultProcessClass);
            }
        }
        return $customProcessConfigs;
    }

    /**
     * @param string $name
     * @param string $processClass
     * @param string $groupName
     * @return ProcessConfig
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function addCustomProcessesConfig(string $name, string $processClass, string $groupName): ProcessConfig
    {
        $processConfig = new ProcessConfig($processClass, $name, $groupName);
        $this->customProcessConfigs[$name] = $processConfig;
        return $processConfig;
    }

    /**
     * @return void
     * @throws \DI\DependencyException
     */
    public function createProcess()
    {
        //Configure the default worker process
        $serverConfig = $this->server->getServerConfig();
        for ($i = 0; $i < $serverConfig->getWorkerNum(); $i++) {
            $defaultProcessClass = $this->getDefaultProcessClass();
            $process = new $defaultProcessClass($this->server, $i, "worker-" . $i, Process::WORKER_GROUP);
            Server::$instance->getContainer()->injectOn($process);
            $this->addProcesses($process);
        }
        $startId = $serverConfig->getWorkerNum();

        //Reacquire configuration
        $this->customProcessConfigs = $this->getCustomProcessConfigs();

        //Configure custom processes
        foreach ($this->customProcessConfigs as $processConfig) {
            $processClass = $processConfig->getClassName();
            $process = new $processClass($this->server, $startId, $processConfig->getName(), $processConfig->getGroupName());
            Server::$instance->getContainer()->injectOn($process);
            $this->addProcesses($process);
            $startId++;
        }
    }

    /**
     * Add process
     *
     * @param Process $process
     */
    protected function addProcesses(Process $process)
    {
        if ($process->getProcessType() == Process::PROCESS_TYPE_CUSTOM) {
            $process->createProcess();
            $this->server->getServer()->addProcess($process->getSwooleProcess());
        }
        $this->processes[$process->getProcessId()] = $process;
    }

    /**
     * Get default process class
     * @return string
     */
    public function getDefaultProcessClass(): string
    {
        return $this->defaultProcessClass;
    }

    /**
     * Get process group
     *
     * @param string $groupName
     * @return ProcessGroup|null
     */
    public function getProcessGroup(string $groupName): ?ProcessGroup
    {
        if (isset($this->groups[$groupName])) {
            return $this->groups[$groupName];
        }
        $group = [];
        foreach ($this->processes as $process) {
            if ($process->getGroupName() == $groupName) {
                $group[] = $process;
            }
        }
        if (empty($group)) {
            return null;
        }

        $processGroup = new ProcessGroup($this, $groupName, $group);
        $this->groups[$groupName] = $processGroup;

        return $processGroup;
    }


    /**
     * Get the PID of the current server's main process
     * @return int|null
     */
    public function getMasterPid(): ?int
    {
        return $this->server->getServer()->master_pid ?? null;
    }

    /**
     * Get the PID of the current server management process.
     * @return int|null
     */
    public function getManagerPid(): ?int
    {
        return $this->server->getServer()->manager_pid ?? null;
    }

    /**
     * Get the number of the current Worker process
     * @return int|null
     */
    public function getCurrentProcessId(): ?int
    {
        return $this->server->getServer()->worker_id ?? null;
    }

    /**
     * Set current process id
     *
     * @param int $processId
     */
    public function setCurrentProcessId(int $processId)
    {
        $this->server->getServer()->worker_id = $processId;
    }

    /**
     * Get the operating system process ID of the current Worker process.
     * Same as the return value of posix_getpid()
     *
     * @return int
     */
    public function getCurrentProcessPid(): int
    {
        return $this->server->getServer()->worker_pid;
    }

    /**
     * Set current process pid
     *
     * @param int $processPid
     */
    public function setCurrentProcessPid(int $processPid)
    {
        $this->server->getServer()->worker_pid = $processPid;
    }

    /**
     * Get current process
     *
     * @return Process
     */
    public function getCurrentProcess(): ?Process
    {
        if ($this->getCurrentProcessId() === null) {
            if ($this->getMasterPid() === null) {
                return $this->masterProcess;
            } else if ($this->getManagerPid() !== null) {
                return $this->managerProcess;
            } else {
                return null;
            }
        }
        return $this->getProcessFromId($this->getCurrentProcessId());
    }

    /**
     * Send message to process group, poll
     *
     * @param $message
     * @param string $groupName
     * @throws \Exception
     */
    public function sendMessageToGroup($message, string $groupName)
    {
        $group = $this->getProcessGroup($groupName);
        if ($group == null) {
            throw new \Exception(sprintf("No %s process group", $groupName));
        }
        $group->sendMessageToGroup($message);
    }

    /**
     * Get processes
     *
     * @return Process[]
     */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    /**
     * @param Process $managerProcess
     */
    public function setManagerProcess(Process $managerProcess): void
    {
        $this->managerProcess = $managerProcess;
    }

    /**
     * Set master process
     *
     * @param Process $masterProcess
     */
    public function setMasterProcess(Process $masterProcess): void
    {
        $this->masterProcess = $masterProcess;
    }

    /**
     * Get manager process
     *
     * @return Process
     */
    public function getManagerProcess(): Process
    {
        return $this->managerProcess;
    }

    /**
     * Get master process
     *
     * @return Process
     */
    public function getMasterProcess(): Process
    {
        return $this->masterProcess;
    }
}
