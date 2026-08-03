<?php

declare(strict_types=1);

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

/**
 * Routes flushed messages from a {@see Logger} to its registered {@see Target}s.
 */
class Dispatcher extends Component
{
    /** @var Target[] */
    public array $targets = [];

    /**
     * When true (default), a target that throws during export is reported via
     * error_log instead of silently swallowed. Set to false to ignore target errors.
     */
    public bool $reportTargetErrors = true;

    /**
     * @param Target ...$targets One or more targets that receive dispatched messages.
     */
    public function __construct(Target ...$targets)
    {
        $this->targets = $targets;
    }

    /**
     * @param Message[] $messages
     */
    public function dispatch(array $messages, bool $final): void
    {
        foreach ($this->targets as $target) {
            if (!$target->getEnabled()) {
                continue;
            }
            try {
                $target->collect($messages, $final);
            } catch (\Throwable $e) {
                if ($this->reportTargetErrors) {
                    error_log(sprintf(
                        '[Actor\\Log] target %s failed: %s',
                        get_class($target),
                        $e->getMessage()
                    ));
                }
            }
        }
    }
}
