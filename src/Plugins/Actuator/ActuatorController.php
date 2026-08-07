<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actuator;

use Yew\Core\Memory\CrossProcess\Table;
use Yew\Coroutine\Server\Server;

/**
 * Exposes /actuator endpoints (health, info) as JSON.
 */
class ActuatorController
{
    public function index(): string
    {
        return json_encode([
            "status" => "UP",
            "server" => "yew-server"
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Per-route request counters over the last 60s / 3600s / 86400s windows.
     */
    public function health(): string
    {
        $output = [];
        try {
            /** @var Table $table */
            $table = DIGet("RouteCountTable");
            foreach ($table as $path => $num) {
                $output[$path] = [$num["num_60"], $num["num_3600"], $num["num_86400"]];
            }
        } catch (\Throwable $e) {
            // table not registered yet
        }
        return json_encode([
            "status" => "UP",
            "route" => $output
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function info(): string
    {
        $serverStats = Server::$instance->stats();
        $output["server"] = "yew-server";
        $output["Start time"] = date("Y-m-d H:i:s", $serverStats->getStartTime());
        $output["Accept count"] = $serverStats->getAcceptCount();
        $output["Close count"] = $serverStats->getCloseCount();
        $output["Request count"] = $serverStats->getRequestCount();
        $output["Coroutine num"] = $serverStats->getCoroutineNum();
        $output["Connection num"] = $serverStats->getConnectionNum();
        $output["Tasking num"] = $serverStats->getTaskingNum();
        $output["TaskQueue bytes"] = $serverStats->getTaskQueueBytes();
        $output["Worker dispatch count"] = $serverStats->getWorkerDispatchCount();
        $output["Worker request count"] = $serverStats->getWorkerRequestCount();
        return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
