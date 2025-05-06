<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Filter;

use Yew\Core\Server\Beans\Http\HttpStream;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Pack\ClientData;
use Yew\Utils\ArrayToXml;

/**
 * Class JsonResponseFilter
 * @package Yew\Plugins\Route\Filter
 */
class JsonResponseFilter extends AbstractFilter
{
    /**
     * @return string
     */
    public function getType()
    {
        return AbstractFilter::FILTER_ROUTE;
    }

    /**
     * @param ClientData $clientData
     * @return int
     */
    public function filter(ClientData $clientData): int
    {
        $annotations = $clientData->getAnnotations();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof ResponseBody && strpos($annotation->value, "application/json") !== false) {
                $data = $clientData->getResponseRaw();

                if (!is_string($data)) {
                    if ($data instanceof HttpStream) {
                        $data = $data->__toString();
                    } else {
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    $clientData->setResponseRaw($data);
                }

                $clientData->getResponse()->withHeader("Content-type", $annotation->value);
                return AbstractFilter::RETURN_NEXT;
            }

        }

        return AbstractFilter::RETURN_NEXT;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "JsonResponseFilter";
    }

    /**
     * @param ClientData $clientData
     * @return bool
     */
    public function isEnable(ClientData $clientData)
    {
        return $this->isHttp($clientData);
    }
}