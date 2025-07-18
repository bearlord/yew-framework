<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Filter;

use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Pack\ClientData;
use Yew\Utils\ArrayToXml;

class XmlResponseFilter extends AbstractFilter
{
    /**
     * @return string
     */
    public function getType(): string
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
            if ($annotation instanceof ResponseBody && strpos($annotation->value, "application/xml") !== false) {
                $data = $clientData->getResponseRaw();

                $xmlStartElement = $annotation->xmlStartElement;
                if (is_array($data)){
                    $data = (new ArrayToXml())->buildXML($data, $xmlStartElement);
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
        return "XmlResponseFilter";
    }

    /**
     * @param ClientData $clientData
     * @return bool
     */
    public function isEnable(ClientData $clientData): bool
    {
        return $this->isHttp($clientData);
    }
}