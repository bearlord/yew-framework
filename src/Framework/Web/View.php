<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Web;

use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Helpers\Json;
use Yew\Framework\Helpers\ArrayHelper;
use Yew\Framework\Helpers\Html;
use Yew\Framework\Helpers\Url;
use Yew\Yew;

/**
 * View represents a view object in the MVC pattern.
 *
 * View provides a set of methods (e.g. [[render()]]) for rendering purpose.
 *
 * View is configured as an application component in [[\Yew\Framework\Base\Application]] by default.
 * You can access that instance via `Yew::$app->view`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * ```php
 * 'view' => [
 *     'theme' => 'app\themes\MyTheme',
 *     'renderers' => [
 *         // you may add Smarty or Twig renderer here
 *     ]
 *     // ...
 * ]
 * ```
 *
 * For more details and usage information on View, see the [guide article on views](guide:structure-views).
 *
 * @property \Yew\Framework\Web\AssetManager $assetManager The asset manager. Defaults to the "assetManager" application
 * component.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class View extends \Yew\Framework\Base\View
{
    /**
     * @event Event an event that is triggered by [[beginBody()]].
     */
    const EVENT_BEGIN_BODY = 'beginBody';
    /**
     * @event Event an event that is triggered by [[endBody()]].
     */
    const EVENT_END_BODY = 'endBody';
    /**
     * The location of registered JavaScript code block or files.
     * This means the location is in the head section.
     */
    const POS_HEAD = 1;
    /**
     * The location of registered JavaScript code block or files.
     * This means the location is at the beginning of the body section.
     */
    const POS_BEGIN = 2;
    /**
     * The location of registered JavaScript code block or files.
     * This means the location is at the end of the body section.
     */
    const POS_END = 3;
    /**
     * The location of registered JavaScript code block.
     * This means the JavaScript code block will be enclosed within `jQuery(document).ready()`.
     */
    const POS_READY = 4;
    /**
     * The location of registered JavaScript code block.
     * This means the JavaScript code block will be enclosed within `jQuery(window).load()`.
     */
    const POS_LOAD = 5;
    /**
     * This is internally used as the placeholder for receiving the content registered for the head section.
     */
    const PH_HEAD = '<![CDATA[YII-BLOCK-HEAD]]>';
    /**
     * This is internally used as the placeholder for receiving the content registered for the beginning of the body section.
     */
    const PH_BODY_BEGIN = '<![CDATA[YII-BLOCK-BODY-BEGIN]]>';
    /**
     * This is internally used as the placeholder for receiving the content registered for the end of the body section.
     */
    const PH_BODY_END = '<![CDATA[YII-BLOCK-BODY-END]]>';

    /**
     * @var AssetBundle[] list of the registered asset bundles. The keys are the bundle names, and the values
     * are the registered [[AssetBundle]] objects.
     * @see registerAssetBundle()
     */
    public array $assetBundles = [];
    /**
     * @var string the page title
     */
    public string $title;
    /**
     * @var array the registered meta tags.
     * @see registerMetaTag()
     */
    public array $metaTags = [];
    /**
     * @var array the registered link tags.
     * @see registerLinkTag()
     */
    public array $linkTags = [];
    /**
     * @var array the registered CSS code blocks.
     * @see registerCss()
     */
    public array $css = [];
    /**
     * @var array the registered CSS files.
     * @see registerCssFile()
     */
    public array $cssFiles = [];
    /**
     * @var array the registered JS code blocks
     * @see registerJs()
     */
    public array $js = [];
    /**
     * @var array the registered JS files.
     * @see registerJsFile()
     */
    public array $jsFiles = [];

    private ?AssetManager $_assetManager = null;


    /**
     * Marks the position of an HTML head section.
     */
    public function head()
    {
        echo self::PH_HEAD;
    }

    /**
     * Marks the beginning of an HTML body section.
     */
    public function beginBody()
    {
        echo self::PH_BODY_BEGIN;
        $this->trigger(self::EVENT_BEGIN_BODY);
    }

    /**
     * Marks the ending of an HTML body section.
     */
    public function endBody()
    {
        $this->trigger(self::EVENT_END_BODY);
        echo self::PH_BODY_END;

        foreach (array_keys($this->assetBundles) as $bundle) {
            $this->registerAssetFiles($bundle);
        }
    }

    /**
     * Marks the ending of an HTML page.
     * @param bool $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions
     * will be rendered at the end of the view like normal scripts.
     */
    public function endPage(bool $ajaxMode = false)
    {
        $this->trigger(self::EVENT_END_PAGE);

        $content = ob_get_clean();

        echo strtr($content, [
            self::PH_HEAD => $this->renderHeadHtml(),
            self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
            self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
        ]);

        $this->clear();
    }

    /**
     * Renders a view in response to an AJAX request.
     *
     * This method is similar to [[render()]] except that it will surround the view being rendered
     * with the calls of [[beginPage()]], [[head()]], [[beginBody()]], [[endBody()]] and [[endPage()]].
     * By doing so, the method is able to inject into the rendering result with JS/CSS scripts and files
     * that are registered with the view.
     *
     * @param string $view the view name. Please refer to [[render()]] on how to specify this parameter.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param object|null $context the context that the view should use for rendering the view. If null,
     * existing [[context]] will be used.
     * @return string the rendering result
     * @throws InvalidConfigException
     * @throws \Throwable
     * @see render()
     */
    public function renderAjax(string $view, array $params = [], object $context = null): string
    {
        $viewFile = $this->findViewFile($view, $context);

        ob_start();
        ob_implicit_flush(false);

        $this->beginPage();
        $this->head();
        $this->beginBody();
        echo $this->renderFile($viewFile, $params, $context);
        $this->endBody();
        $this->endPage(true);

        return ob_get_clean();
    }

    /**
     * Registers the asset manager being used by this view object.
     * @return \Yew\Framework\Web\AssetManager the asset manager. Defaults to the "assetManager" application component.
     */
    public function getAssetManager(): AssetManager
    {
        return $this->_assetManager ?: Yew::$app->getAssetManager();
    }

    /**
     * Sets the asset manager.
     * @param \Yew\Framework\Web\AssetManager $value the asset manager
     */
    public function setAssetManager(AssetManager $value)
    {
        $this->_assetManager = $value;
    }

    /**
     * Clears up the registered meta tags, link tags, css/js scripts and files.
     */
    public function clear()
    {
        $this->metaTags = [];
        $this->linkTags = [];
        $this->css = [];
        $this->cssFiles = [];
        $this->js = [];
        $this->jsFiles = [];
        $this->assetBundles = [];
    }

    /**
     * Registers all files provided by an asset bundle including depending bundles files.
     * Removes a bundle from [[assetBundles]] once files are registered.
     * @param string $name name of the bundle to register
     */
    protected function registerAssetFiles(string $name)
    {
        if (!isset($this->assetBundles[$name])) {
            return;
        }
        $bundle = $this->assetBundles[$name];
        if ($bundle) {
            foreach ($bundle->depends as $dep) {
                $this->registerAssetFiles($dep);
            }
            $bundle->registerAssetFiles($this);
        }
        unset($this->assetBundles[$name]);
    }

    /**
     * Registers the named asset bundle.
     * All dependent asset bundles will be registered.
     * @param string $name the class name of the asset bundle (without the leading backslash)
     * @param int|null $position if set, this forces a minimum position for javascript files.
     * This will adjust depending assets javascript file position or fail if requirement can not be met.
     * If this is null, asset bundles position settings will not be changed.
     * See [[registerJsFile]] for more details on javascript position.
     * @return AssetBundle the registered asset bundle instance
     * @throws InvalidConfigException if the asset bundle does not exist or a circular dependency is detected
     */
    public function registerAssetBundle(string $name, int $position = null): AssetBundle
    {
        if (!isset($this->assetBundles[$name])) {
            $am = $this->getAssetManager();
            $bundle = $am->getBundle($name);
            $this->assetBundles[$name] = false;
            // register dependencies
            $pos = $bundle->jsOptions['position'] ?? null;
            foreach ($bundle->depends as $dep) {
                $this->registerAssetBundle($dep, $pos);
            }
            $this->assetBundles[$name] = $bundle;
        } elseif ($this->assetBundles[$name] === false) {
            throw new InvalidConfigException("A circular dependency is detected for bundle '$name'.");
        } else {
            $bundle = $this->assetBundles[$name];
        }

        if ($position !== null) {
            $pos = $bundle->jsOptions['position'] ?? null;
            if ($pos === null) {
                $bundle->jsOptions['position'] = $pos = $position;
            } elseif ($pos > $position) {
                throw new InvalidConfigException("An asset bundle that depends on '$name' has a higher javascript file position configured than '$name'.");
            }
            // update position for all dependencies
            foreach ($bundle->depends as $dep) {
                $this->registerAssetBundle($dep, $pos);
            }
        }

        return $bundle;
    }

    /**
     * Registers a meta tag.
     *
     * For example, a description meta tag can be added like the following:
     *
     * ```php
     * $view->registerMetaTag([
     *     'name' => 'description',
     *     'content' => 'This website is about funny raccoons.'
     * ]);
     * ```
     *
     * will result in the meta tag `<meta name="description" content="This website is about funny raccoons.">`.
     *
     * @param array $options the HTML attributes for the meta tag.
     * @param string|null $key the key that identifies the meta tag. If two meta tags are registered
     * with the same key, the latter will overwrite the former. If this is null, the new meta tag
     * will be appended to the existing ones.
     */
    public function registerMetaTag(array $options, string $key = null)
    {
        if ($key === null) {
            $this->metaTags[] = Html::tag('meta', '', $options);
        } else {
            $this->metaTags[$key] = Html::tag('meta', '', $options);
        }
    }

    /**
     * Registers CSRF meta tags.
     * They are rendered dynamically to retrieve a new CSRF token for each request.
     *
     * ```php
     * $view->registerCsrfMetaTags();
     * ```
     *
     * The above code will result in `<meta name="csrf-param" content="[Yew\Framework\Web\Request::$csrfParam]">`
     * and `<meta name="csrf-token" content="tTNpWKpdy-bx8ZmIq9R72...K1y8IP3XGkzZA==">` added to the page.
     *
     * Note: Hidden CSRF input of ActiveForm will be automatically refreshed by calling `window.yii.refreshCsrfToken()`
     * from `yii.js`.
     *
     * @since 2.0.13
     */
    public function registerCsrfMetaTags()
    {
        $this->metaTags['csrf_meta_tags'] = $this->renderDynamic('return Yew\Framework\Helpers\Html::csrfMetaTags();');
    }

    /**
     * Registers a link tag.
     *
     * For example, a link tag for a custom [favicon](http://www.w3.org/2005/10/howto-favicon)
     * can be added like the following:
     *
     * ```php
     * $view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'href' => '/myicon.png']);
     * ```
     *
     * which will result in the following HTML: `<link rel="icon" type="image/png" href="/myicon.png">`.
     *
     * **Note:** To register link tags for CSS stylesheets, use [[registerCssFile()]] instead, which
     * has more options for this kind of link tag.
     *
     * @param array $options the HTML attributes for the link tag.
     * @param string|null $key the key that identifies the link tag. If two link tags are registered
     * with the same key, the latter will overwrite the former. If this is null, the new link tag
     * will be appended to the existing ones.
     */
    public function registerLinkTag(array $options, string $key = null)
    {
        if ($key === null) {
            $this->linkTags[] = Html::tag('link', '', $options);
        } else {
            $this->linkTags[$key] = Html::tag('link', '', $options);
        }
    }

    /**
     * Registers a CSS code block.
     * @param string $css the content of the CSS code block to be registered
     * @param array $options the HTML attributes for the `<style>`-tag.
     * @param string|null $key the key that identifies the CSS code block. If null, it will use
     * $css as the key. If two CSS code blocks are registered with the same key, the latter
     * will overwrite the former.
     */
    public function registerCss(string $css, array $options = [], string $key = null)
    {
        $key = $key ?: md5($css);
        $this->css[$key] = Html::style($css, $options);
    }

    /**
     * Registers a CSS file.
     *
     * This method should be used for simple registration of CSS files. If you want to use features of
     * [[AssetManager]] like appending timestamps to the URL and file publishing options, use [[AssetBundle]]
     * and [[registerAssetBundle()]] instead.
     *
     * @param string $url the CSS file to be registered.
     * @param array $options the HTML attributes for the link tag. Please refer to [[Html::cssFile()]] for
     * the supported options. The following options are specially handled and are not treated as HTML attributes:
     *
     * - `depends`: array, specifies the names of the asset bundles that this CSS file depends on.
     * - `appendTimestamp`: bool whether to append a timestamp to the URL.
     *
     * @param string|null $key the key that identifies the CSS script file. If null, it will use
     * $url as the key. If two CSS files are registered with the same key, the latter
     * will overwrite the former.
     * @throws InvalidConfigException
     */
    public function registerCssFile(string $url, array $options = [], string $key = null)
    {
        $this->registerFile('css', $url, $options, $key);
    }

    /**
     * Registers a JS code block.
     * @param string $js the JS code block to be registered
     * @param int $position the position at which the JS script tag should be inserted
     * in a page. The possible values are:
     *
     * - [[POS_HEAD]]: in the head section
     * - [[POS_BEGIN]]: at the beginning of the body section
     * - [[POS_END]]: at the end of the body section
     * - [[POS_LOAD]]: enclosed within jQuery(window).load().
     *   Note that by using this position, the method will automatically register the jQuery js file.
     * - [[POS_READY]]: enclosed within jQuery(document).ready(). This is the default value.
     *   Note that by using this position, the method will automatically register the jQuery js file.
     *
     * @param string|null $key the key that identifies the JS code block. If null, it will use
     * $js as the key. If two JS code blocks are registered with the same key, the latter
     * will overwrite the former.
     */
    public function registerJs(string $js, int $position = self::POS_READY, string $key = null)
    {
        $key = $key ?: md5($js);
        $this->js[$position][$key] = $js;
        if ($position === self::POS_READY || $position === self::POS_LOAD) {
            JqueryAsset::register($this);
        }
    }

    /**
     * Registers a JS or CSS file.
     *
     * @param string $url the JS file to be registered.
     * @param string $type type (js or css) of the file.
     * @param array $options the HTML attributes for the script tag. The following options are specially handled
     * and are not treated as HTML attributes:
     *
     * - `depends`: array, specifies the names of the asset bundles that this CSS file depends on.
     * - `appendTimestamp`: bool whether to append a timestamp to the URL.
     *
     * @param string|null $key the key that identifies the JS script file. If null, it will use
     * $url as the key. If two JS files are registered with the same key at the same position, the latter
     * will overwrite the former. Note that position option takes precedence, thus files registered with the same key,
     * but different position option will not override each other.
     * @throws InvalidConfigException
     */
    private function registerFile(string $type, string $url, array $options = [], string $key = null)
    {
        $url = Yew::getAlias($url);
        $key = $key ?: $url;
        $depends = ArrayHelper::remove($options, 'depends', []);
        $position = ArrayHelper::remove($options, 'position', self::POS_END);

        try {
            $asssetManagerAppendTimestamp = $this->getAssetManager()->appendTimestamp;
        } catch (InvalidConfigException $e) {
            $depends = null; // the AssetManager is not available
            $asssetManagerAppendTimestamp = false;
        }
        $appendTimestamp = ArrayHelper::remove($options, 'appendTimestamp', $asssetManagerAppendTimestamp);

        if (empty($depends)) {
            // register directly without AssetManager
            if ($appendTimestamp && Url::isRelative($url) && ($timestamp = @filemtime(Yew::getAlias('@webroot/' . ltrim($url, '/'), false))) > 0) {
                $url = $timestamp ? "$url?v=$timestamp" : $url;
            }
            if ($type === 'js') {
                $this->jsFiles[$position][$key] = Html::jsFile($url, $options);
            } else {
                $this->cssFiles[$key] = Html::cssFile($url, $options);
            }
        } else {
            $this->getAssetManager()->bundles[$key] = Yew::createObject([
                'class' => AssetBundle::class,
                'baseUrl' => '',
                'basePath' => '@webroot',
                (string)$type => [!Url::isRelative($url) ? $url : ltrim($url, '/')],
                "{$type}Options" => $options,
                'depends' => (array)$depends,
            ]);
            $this->registerAssetBundle($key);
        }
    }

    /**
     * Registers a JS file.
     *
     * This method should be used for simple registration of JS files. If you want to use features of
     * [[AssetManager]] like appending timestamps to the URL and file publishing options, use [[AssetBundle]]
     * and [[registerAssetBundle()]] instead.
     *
     * @param string $url the JS file to be registered.
     * @param array $options the HTML attributes for the script tag. The following options are specially handled
     * and are not treated as HTML attributes:
     *
     * - `depends`: array, specifies the names of the asset bundles that this JS file depends on.
     * - `position`: specifies where the JS script tag should be inserted in a page. The possible values are:
     *     * [[POS_HEAD]]: in the head section
     *     * [[POS_BEGIN]]: at the beginning of the body section
     *     * [[POS_END]]: at the end of the body section. This is the default value.
     * - `appendTimestamp`: bool whether to append a timestamp to the URL.
     *
     * Please refer to [[Html::jsFile()]] for other supported options.
     *
     * @param string|null $key the key that identifies the JS script file. If null, it will use
     * $url as the key. If two JS files are registered with the same key at the same position, the latter
     * will overwrite the former. Note that position option takes precedence, thus files registered with the same key,
     * but different position option will not override each other.
     * @throws InvalidConfigException
     */
    public function registerJsFile(string $url, array $options = [], string $key = null)
    {
        $this->registerFile('js', $url, $options, $key);
    }

    /**
     * Registers a JS code block defining a variable. The name of variable will be
     * used as key, preventing duplicated variable names.
     *
     * @param string $name Name of the variable
     * @param array|string $value Value of the variable
     * @param int $position the position in a page at which the JavaScript variable should be inserted.
     * The possible values are:
     *
     * - [[POS_HEAD]]: in the head section. This is the default value.
     * - [[POS_BEGIN]]: at the beginning of the body section.
     * - [[POS_END]]: at the end of the body section.
     * - [[POS_LOAD]]: enclosed within jQuery(window).load().
     *   Note that by using this position, the method will automatically register the jQuery js file.
     * - [[POS_READY]]: enclosed within jQuery(document).ready().
     *   Note that by using this position, the method will automatically register the jQuery js file.
     *
     * @since 2.0.14
     */
    public function registerJsVar(string $name, $value, int $position = self::POS_HEAD)
    {
        $js = sprintf('var %s = %s;', $name, Json::htmlEncode($value));
        $this->registerJs($js, $position, $name);
    }

    /**
     * Renders the content to be inserted in the head section.
     * The content is rendered using the registered meta tags, link tags, CSS/JS code blocks and files.
     * @return string the rendered content
     */
    protected function renderHeadHtml(): string
    {
        $lines = [];
        if (!empty($this->metaTags)) {
            $lines[] = implode("\n", $this->metaTags);
        }

        if (!empty($this->linkTags)) {
            $lines[] = implode("\n", $this->linkTags);
        }
        if (!empty($this->cssFiles)) {
            $lines[] = implode("\n", $this->cssFiles);
        }
        if (!empty($this->css)) {
            $lines[] = implode("\n", $this->css);
        }
        if (!empty($this->jsFiles[self::POS_HEAD])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_HEAD]);
        }
        if (!empty($this->js[self::POS_HEAD])) {
            $lines[] = Html::script(implode("\n", $this->js[self::POS_HEAD]));
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * Renders the content to be inserted at the beginning of the body section.
     * The content is rendered using the registered JS code blocks and files.
     * @return string the rendered content
     */
    protected function renderBodyBeginHtml(): string
    {
        $lines = [];
        if (!empty($this->jsFiles[self::POS_BEGIN])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_BEGIN]);
        }
        if (!empty($this->js[self::POS_BEGIN])) {
            $lines[] = Html::script(implode("\n", $this->js[self::POS_BEGIN]));
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * Renders the content to be inserted at the end of the body section.
     * The content is rendered using the registered JS code blocks and files.
     * @param bool $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions
     * will be rendered at the end of the view like normal scripts.
     * @return string the rendered content
     */
    protected function renderBodyEndHtml(bool $ajaxMode): string
    {
        $lines = [];

        if (!empty($this->jsFiles[self::POS_END])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_END]);
        }

        if ($ajaxMode) {
            $scripts = [];
            if (!empty($this->js[self::POS_END])) {
                $scripts[] = implode("\n", $this->js[self::POS_END]);
            }
            if (!empty($this->js[self::POS_READY])) {
                $scripts[] = implode("\n", $this->js[self::POS_READY]);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $scripts[] = implode("\n", $this->js[self::POS_LOAD]);
            }
            if (!empty($scripts)) {
                $lines[] = Html::script(implode("\n", $scripts));
            }
        } else {
            if (!empty($this->js[self::POS_END])) {
                $lines[] = Html::script(implode("\n", $this->js[self::POS_END]));
            }
            if (!empty($this->js[self::POS_READY])) {
                $js = "jQuery(function ($) {\n" . implode("\n", $this->js[self::POS_READY]) . "\n});";
                $lines[] = Html::script($js);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $js = "jQuery(window).on('load', function () {\n" . implode("\n", $this->js[self::POS_LOAD]) . "\n});";
                $lines[] = Html::script($js);
            }
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }
}
