<?php

namespace Yew\Framework\Config;

use Symfony\Component\Yaml\Yaml;

class ConfigFactory
{

    /**
     * @return Config|null
     * @throws \Exception
     */
    public static function build(): ?Config
    {
        if (!defined("RES_DIR")){
            return null;
        }

        $baseFile = __DIR__ . '/resources/base.yml';
        $config = new Config(Yaml::parse(file_get_contents($baseFile)));


        $applicationFile = RES_DIR . '/application.yml';
        if (!file_exists($applicationFile)) {
            return null;
        }
        $config->addMultiple(Yaml::parse(file_get_contents($applicationFile)));

        $active = $config->get('yew.profiles.active');
        if (!empty($active)) {
            $activeFile = RES_DIR. "/application-$active.yml";
            if (file_exists($activeFile)) {
                $config->addMultiple(Yaml::parseFile($activeFile));
            }
        }

        return $config;
    }


}