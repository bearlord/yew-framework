<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Generator;

use Yew\Framework\Base\BootstrapInterface;
use Yew\Yew;
use Yew\Framework\Helpers\Json;
use Yew\Framework\Web\ForbiddenHttpException;

/**
 * This is the main module class for the Gii module.
 *
 * To use Gii, include it as a module in the application configuration like the following:
 *
 * ~~~
 * return [
 *     'bootstrap' => ['gii'],
 *     'modules' => [
 *         'gii' => ['class' => 'yii\gii\Module'],
 *     ],
 * ]
 * ~~~
 *
 * Because Gii generates new code files on the server, you should only use it on your own
 * development machine. To prevent other people from using this module, by default, Gii
 * can only be accessed by localhost. You may configure its [[allowedIPs]] property if
 * you want to make it accessible on other machines.
 *
 * With the above configuration, you will be able to access GiiModule in your browser using
 * the URL `http://localhost/path/to/index.php?r=gii`
 *
 * If your application enables [[\yii\web\UrlManager::enablePrettyUrl|pretty URLs]],
 * you can then access Gii via URL: `http://localhost/path/to/index.php/gii`
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Module extends \Yew\Framework\Base\Module implements BootstrapInterface
{
    /**
     * {@inheritdoc}
     */
    public ?string $controllerNamespace = 'Yew\Framework\Generator\Console\Controllers';
    

    /**
     * @var array|Generator[] a list of generator configurations or instances. The array keys
     * are the generator IDs (e.g. "crud"), and the array elements are the corresponding generator
     * configurations or the instances.
     *
     * After the module is initialized, this property will become an array of generator instances
     * which are created based on the configurations previously taken by this property.
     *
     * Newly assigned generators will be merged with the [[coreGenerators()|core ones]], and the former
     * takes precedence in case when they have the same generator ID.
     */
    public array $generators = [];

    /**
     * @var int the permission to be set for newly generated code files.
     * This value will be used by PHP chmod function.
     * Defaults to 0666, meaning the file is read-writable by all users.
     */
    public int $newFileMode = 0666;

    /**
     * @var int the permission to be set for newly generated directories.
     * This value will be used by PHP chmod function.
     * Defaults to 0777, meaning the directory can be read, written and executed by all users.
     */
    public int $newDirMode = 0777;



    /**
     * Sets the currently requested instance of this module class.
     * @param Module|null $instance the currently requested instance of this module class.
     * If it is `null`, the instance of the calling class will be removed, if any.
     */
    public static function setInstance($instance)
    {
        if ($instance === null) {
            unset(Yew::$app->loadedModules[get_called_class()]);
        } else {
            Yew::$app->loadedModules[get_class($instance)] = $instance;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap($app)
    {
        if ($app instanceof \Yew\Framework\Console\Application) {
            $app->controllerMap[$this->id] = [
                'class' => 'Yew\Framework\Generator\Console\GenerateController',
                'generators' => array_merge($this->coreGenerators(), $this->generators),
                'module' => $this,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        foreach (array_merge($this->coreGenerators(), $this->generators) as $id => $config) {
            if (is_object($config)) {
                $this->generators[$id] = $config;
            } else {
                $this->generators[$id] = Yew::createObject($config);
            }
        }
        
        return true;
    }
    

    /**
     * Returns the list of the core code generator configurations.
     * @return array the list of the core code generator configurations.
     */
    protected function coreGenerators()
    {
        return [
            'model' => ['class' => 'Yew\Framework\Generator\Model\Generator'],
//            'controller' => ['class' => 'Yew\Framework\Generator\Controller\Generator'],
//            'module' => ['class' => 'Yew\Framework\Generator\Module\Generator'],
        ];
    }

}
