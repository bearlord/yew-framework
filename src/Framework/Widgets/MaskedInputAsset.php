<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Widgets;

use Yew\Framework\Web\AssetBundle;

/**
 * The asset bundle for the [[MaskedInput]] widget.
 *
 * Includes client assets of [jQuery input mask plugin](https://github.com/RobinHerbots/Inputmask).
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @since 2.0
 */
class MaskedInputAsset extends AssetBundle
{
    public $sourcePath = '@bower/inputmask/dist';
    public $js = [
        'jquery.inputmask.bundle.js',
    ];
    public $depends = [
        'Yew\Framework\Web\YiiAsset',
    ];
}
