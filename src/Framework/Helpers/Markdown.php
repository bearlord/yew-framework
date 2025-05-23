<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Helpers;

/**
 * Markdown provides an ability to transform markdown into HTML.
 *
 * Basic usage is the following:
 *
 * ```php
 * $myHtml = Markdown::process($myText); // use original markdown flavor
 * $myHtml = Markdown::process($myText, 'gfm'); // use github flavored markdown
 * $myHtml = Markdown::process($myText, 'extra'); // use markdown extra
 * ```
 *
 * You can configure multiple flavors using the [[$flavors]] property.
 *
 * For more details please refer to the [Markdown library documentation](https://github.com/cebe/markdown#readme).
 *
 * > Note: The Markdown library works with PHPDoc annotations so if you use it together with
 * > PHP `opcache` make sure [it does not strip comments](https://secure.php.net/manual/en/opcache.configuration.php#ini.opcache.save-comments).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Markdown extends BaseMarkdown
{
}
