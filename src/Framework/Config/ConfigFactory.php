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

        $applicationFile = RES_DIR . '/application.yml';
        if (!file_exists($applicationFile)) {
            return null;
        }

        $config = new Config(Yaml::parseFile($applicationFile));

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