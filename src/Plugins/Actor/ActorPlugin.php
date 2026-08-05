<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Ipc\IpcPlugin;
use Yew\Cluster\ClusterConfig;
use Yew\Cluster\ClusterPlugin;
use Yew\Plugins\Actor\ActorManager;

class ActorPlugin extends AbstractPlugin
{

    /**
     * @var ActorConfig|null
     */
    private ?ActorConfig $actorConfig;

    /**
     * @var ActorManager
     */
    protected ActorManager $actorManager;

    public function __construct()
    {
        parent::__construct();

        $this->initConfig();

        $this->atAfter(IpcPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new IpcPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Actor";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->actorConfig->merge();
        for ($i = 0; $i < $this->actorConfig->getWorkerCount(); $i++) {
            Server::$instance->addProcess("actor-$i", ActorProcess::class, ActorConfig::GROUP_NAME);
        }
        
        $this->actorManager = ActorManager::getInstance();

        // Cluster seam: the dedicated ClusterPlugin (configured from the
        // top-level "yew.cluster" subtree) owns gossip membership, the
        // consistent-hash shard router and the cross-node transport. ActorPlugin
        // only registers the cross-node supervision handler here; actual cluster
        // assembly happens in ClusterPlugin::beforeServerStart.
        $clusterPlugin = DIGet(ClusterPlugin::class);
        if ($clusterPlugin instanceof ClusterPlugin) {
            $clusterPlugin->onNodeDown(function (string $actorName) {
                $this->failoverFrom($actorName);
            });
        }
        return;
    }

    /**
     * User-registered cross-node supervision handler. Invoked for each persisted
     * actor that should be resurrected on this node after its owning node died.
     * The handler is responsible for re-creating the actor (the ClusterActorStore
     * injected into it will recover its state from the replicated event log).
     *
     * @var callable(string):void|null
     */
    private $failoverHandler = null;

    /**
     * Register a handler called when a peer node fails and one of its persisted
     * actors now hashes to this node. Signature: (string $actorName) => void.
     *
     * @param callable(string):void $cb
     * @return void
     */
    public function onNodeDown(callable $cb): void
    {
        $this->failoverHandler = $cb;
    }

    /**
     * Cross-node supervision: a peer node died and the dedicated ClusterPlugin
     * already filtered the replicated actors that now hash to this node and are
     * not already alive here. Invoke the user-registered handler so it can
     * resurrect the actor (rebuilt from the replicated event log, so no state is
     * lost across the failure).
     */
    private function failoverFrom(string $actorName): void
    {
        if ($this->failoverHandler === null) {
            return;
        }
        ($this->failoverHandler)($actorName);
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();

        // Optional lifecycle-hook smoke test. Off by default; enable with:
        //   YEW_RUN_LIFECYCLE_SMOKE=1 php server.php
        // Runs once inside the actor-0 process after startup.
        if (getenv('YEW_RUN_LIFECYCLE_SMOKE') !== false
            && Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === "actor-0"
        ) {
            \Swoole\Coroutine::create(function () {
                $candidates = [];
                if (defined('ROOT_DIR')) {
                    $candidates[] = ROOT_DIR . 'test/Actor/LifecycleHookSmoke.php';
                }
                $candidates[] = (getcwd() ?: __DIR__) . '/test/Actor/LifecycleHookSmoke.php';
                foreach ($candidates as $file) {
                    // cygwin shells expose /cygdrive/d/... paths that the native
                    // swoole-cli binary cannot stat; map them back to Windows form.
                    if (str_starts_with($file, '/cygdrive/')) {
                        $file = preg_replace('#^/cygdrive/([a-z])/#i', '$1:/', $file);
                    }
                    if (is_file($file)) {
                        require_once $file;
                        \App\Test\runLifecycleHookSmoke();
                        return;
                    }
                }
            });
        }
    }

	/**
	 * @return void
	 */
	protected function initConfig()
	{
		$config = Server::$instance->getConfigContext()->get("yew.actor") ?? [];

		$actorConfig = new ActorConfig();

		$actorConfig->setMaxCount((int) ($config["maxCount"] ?? 10000));
		$actorConfig->setWorkerCount((int) ($config["workerCount"] ?? 1));
		$actorConfig->setMaxClassCount((int) ($config["maxClassCount"] ?? 100));
		$actorConfig->setMailboxCapacity((int) ($config["mailboxCapacity"] ?? 100));
		$actorConfig->setMailboxOverflow((string) ($config["mailboxOverflow"] ?? "block"));
		$actorConfig->setMailboxPushTimeout((float) ($config["mailboxPushTimeout"] ?? 1.0));
		$actorConfig->setSupervisorStrategy((string) ($config["supervisorStrategy"] ?? "restart"));
		$actorConfig->setSupervisorMaxRetries((int) ($config["supervisorMaxRetries"] ?? 3));
		$actorConfig->setSupervisorMode((string) ($config["supervisorMode"] ?? "one-for-one"));
		$actorConfig->setPersistenceEnabled((bool) ($config["persistenceEnabled"] ?? false));
		$actorConfig->setPersistenceDir((string) ($config["persistenceDir"] ?? "/tmp/yew-actor-store"));
		$actorConfig->setRoutingStrategy((string) ($config["routingStrategy"] ?? "round-robin"));
		$actorConfig->setRoutingReplicas((int) ($config["routingReplicas"] ?? 128));
		$actorConfig->setDispatcher((string) ($config["dispatcher"] ?? "coroutine"));
		$actorConfig->setDispatcherPoolSize((int) ($config["dispatcherPoolSize"] ?? 4));
		$actorConfig->setTelemetryEnabled((bool) ($config["telemetryEnabled"] ?? false));

		// Cluster settings now live under the top-level "yew.cluster" subtree
		// (decoupled from yew.actor). Build a dedicated ClusterConfig and mirror
		// it onto ActorConfig for code that still reads via ActorConfig getters.
		$clusterCfg = (array) (Server::$instance->getConfigContext()->get("yew.cluster") ?? []);
		$clusterConfig = DIGet(ClusterConfig::class) ?? new ClusterConfig();
		$clusterConfig->buildFromArray($clusterCfg);
		DISet(ClusterConfig::class, $clusterConfig);
		$actorConfig->applyClusterCompat($clusterConfig);

		\Yew\Plugins\Actor\Telemetry\ActorTelemetry::enable($actorConfig->isTelemetryEnabled());

		$this->actorConfig = $actorConfig;
		// Register as a container singleton so Actor::injectOn() and
		// ActorManager::DIGet(ActorConfig::class) resolve the configured instance
		// instead of leaving Actor::$actorConfig uninitialized (typed property).
		DISet(ActorConfig::class, $actorConfig);
	}
}