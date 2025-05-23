<?php

namespace Yew\Nikic\FastRoute;

if (!function_exists('Yew\Nikic\FastRoute\simpleDispatcher')) {
    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function simpleDispatcher(callable $routeDefinitionCallback, array $options = []): Dispatcher
    {
        $options += [
            'routeParser' => 'Yew\\Nikic\\FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'Yew\\Nikic\\FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'Yew\\Nikic\\FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => "Yew\\Nikic\\FastRoute\\RouteCollector",
        ];

        /** @var RouteCollector $routeCollector */
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($routeCollector);

        return new $options['dispatcher']($routeCollector->getData());
    }

    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function cachedDispatcher(callable $routeDefinitionCallback, array $options = []): Dispatcher
    {
        $options += [
            'routeParser' => 'Yew\\Nikic\\FastRoute\\RouteParser\Std',
            'dataGenerator' => 'Yew\\Nikic\\FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'Yew\\Nikic\\FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => "Yew\\Nikic\\FastRoute\\RouteCollector",
            'cacheDisabled' => false,
        ];

        if (!isset($options['cacheFile'])) {
            throw new \LogicException('Must specify "cacheFile" option');
        }

        if (!$options['cacheDisabled'] && file_exists($options['cacheFile'])) {
            $dispatchData = require $options['cacheFile'];
            if (!is_array($dispatchData)) {
                throw new \RuntimeException('Invalid cache file "' . $options['cacheFile'] . '"');
            }
            return new $options['dispatcher']($dispatchData);
        }

        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($routeCollector);

        /** @var RouteCollector $routeCollector */
        $dispatchData = $routeCollector->getData();
        if (!$options['cacheDisabled']) {
            file_put_contents(
                $options['cacheFile'],
                '<?php return ' . var_export($dispatchData, true) . ';'
            );
        }

        return new $options['dispatcher']($dispatchData);
    }
}
