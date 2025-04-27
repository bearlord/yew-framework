<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Autostart;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Actor\ActorPlugin;
use Yew\Plugins\AnnotationsScan\AnnotationsScanPlugin;
use Yew\Plugins\AnnotationsScan\ScanClass;
use Yew\Plugins\Autostart\Annotation\Autostart;
use Yew\Coroutine\Server\Server;
use Swoole\Timer;

class AutostartPlugin extends AbstractPlugin
{
    use GetLogger;

    const PROCESS_NAME = "helper";

    const PROCESS_GROUP_NAME = "HelperGroup";

    public function __construct()
    {
        parent::__construct();

        $this->atAfter(AnnotationsScanPlugin::class);
        $this->atAfter(ActorPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AnnotationsScanPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Autostart";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        Server::$instance->addProcess(self::PROCESS_NAME, HelpAutostartProcess::class, self::PROCESS_GROUP_NAME);
    }

    /**
     * @param Context $context
     * @return void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function beforeProcessStart(Context $context)
    {
        //Help process
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === self::PROCESS_NAME) {
            //Scan annotation
            $scanClass = Server::$instance->getContainer()->get(ScanClass::class);
            $reflectionMethods = $scanClass->findMethodsByAnnotation(Autostart::class);

            $taskList = [];
            foreach ($reflectionMethods as $reflectionMethod) {
                $reflectionClass = $reflectionMethod->getParentReflectClass();
                /** @var Autostart $autostart */
                $autostart = $scanClass->getCachedReader()->getMethodAnnotation($reflectionMethod->getReflectionMethod(), Autostart::class);
                if ($autostart instanceof Autostart) {
                    if (empty($autostart->name)) {
                        $autostart->name = $reflectionClass->getName() . "::" . $reflectionMethod->getName();
                    }
                    if (empty($autostart->delay)) {
                        $autostart->delay = 0;
                    }

                    $taskList[$autostart->sort] = [
                        'class' => $reflectionClass->getName(),
                        'method' => $reflectionMethod->getName(),
                        'delay' => $autostart->delay
                    ];
                }
            }

            if (!empty($taskList)) {
                ksort($taskList);
                foreach ($taskList as $key => $value) {
                    Timer::after($value['delay'] * 1000, function () use ($value) {
                        call_user_func([new $value['class'], $value['method']]);
                    });
                }
            }
        }

        $this->ready();
    }

    protected function customerOrderCallback($reflectionMethodA, $reflectionMethodB)
    {

    }
}