<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Widgets;

use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Base\Widget;
use Yew\Framework\Data\Sort;
use Yew\Framework\Helpers\Html;

/**
 * LinkSorter renders a list of sort links for the given sort definition.
 *
 * LinkSorter will generate a hyperlink for every attribute declared in [[sort]].
 *
 * For more details and usage information on LinkSorter, see the [guide article on sorting](guide:output-sorting).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class LinkSorter extends Widget
{
    /**
     * @var Sort the sort definition
     */
    public $sort;
    /**
     * @var array list of the attributes that support sorting. If not set, it will be determined
     * using [[Sort::attributes]].
     */
    public $attributes;
    /**
     * @var array HTML attributes for the sorter container tag.
     * @see \Yew\Framework\Helpers\Html::ul() for special attributes.
     * @see \Yew\Framework\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'sorter'];
    /**
     * @var array HTML attributes for the link in a sorter container tag which are passed to [[Sort::link()]].
     * @see \Yew\Framework\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     * @since 2.0.6
     */
    public $linkOptions = [];


    /**
     * Initializes the sorter.
     */
    public function init()
    {
        parent::init();

        if ($this->sort === null) {
            throw new InvalidConfigException('The "sort" property must be set.');
        }
    }

    /**
     * Executes the widget.
     * This method renders the sort links.
     */
    public function run()
    {
        echo $this->renderSortLinks();
    }

    /**
     * Renders the sort links.
     * @return string the rendering result
     */
    protected function renderSortLinks()
    {
        $attributes = empty($this->attributes) ? array_keys($this->sort->attributes) : $this->attributes;
        $links = [];
        foreach ($attributes as $name) {
            $links[] = $this->sort->link($name, $this->linkOptions);
        }

        return Html::ul($links, array_merge($this->options, ['encode' => false]));
    }
}
