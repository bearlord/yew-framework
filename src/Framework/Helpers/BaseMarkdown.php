<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Helpers;


use Yew\Framework\Exception\InvalidArgumentException;
use Yew\Coroutine\Server\Server;

/**
 * BaseMarkdown provides concrete implementation for [[Markdown]].
 *
 * Do not use BaseMarkdown. Use [[Markdown]] instead.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class BaseMarkdown
{
    /**
     * @var array a map of markdown flavor names to corresponding parser class configurations.
     */
    public static $flavors = [
        'original' => [
            'class' => 'cebe\markdown\Markdown',
            'html5' => true,
        ],
        'gfm' => [
            'class' => 'cebe\markdown\GithubMarkdown',
            'html5' => true,
        ],
        'gfm-comment' => [
            'class' => 'cebe\markdown\GithubMarkdown',
            'html5' => true,
            'enableNewlines' => true,
        ],
        'extra' => [
            'class' => 'cebe\markdown\MarkdownExtra',
            'html5' => true,
        ],
    ];
    /**
     * @var string the markdown flavor to use when none is specified explicitly.
     * Defaults to `original`.
     * @see $flavors
     */
    public static $defaultFlavor = 'original';


    public function create($params)
    {
        // TODO: Implement create() method.
    }


    /**
     * Converts markdown into HTML.
     *
     * @param string $markdown the markdown text to parse
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return string the parsed HTML output
     * @throws InvalidArgumentException when an undefined flavor is given.
     */
    public static function process($markdown, $flavor = null)
    {
        $parser = static::getParser($flavor);

        return $parser->parse($markdown);
    }

    /**
     * Converts markdown into HTML but only parses inline elements.
     *
     * This can be useful for parsing small comments or description lines.
     *
     * @param string $markdown the markdown text to parse
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return string the parsed HTML output
     * @throws InvalidArgumentException when an undefined flavor is given.
     */
    public static function processParagraph($markdown, $flavor = null)
    {
        $parser = static::getParser($flavor);

        return $parser->parseParagraph($markdown);
    }

    /**
     * @param string $flavor the markdown flavor to use. See [[$flavors]] for available values.
     * Defaults to [[$defaultFlavor]], if not set.
     * @return \cebe\markdown\Parser
     * @throws InvalidArgumentException when an undefined flavor is given.
     */
    protected static function getParser($flavor)
    {
        if ($flavor === null) {
            $flavor = static::$defaultFlavor;
        }
        /* @var $parser \cebe\markdown\Markdown */
        if (!isset(static::$flavors[$flavor])) {
            throw new InvalidArgumentException("Markdown flavor '$flavor' is not defined.'");
        } elseif (!is_object($config = static::$flavors[$flavor])) {
            $object = new $config['class'];
            foreach ($config as $k => $v) {
                if(property_exists($object, $k)) {
                    $object->$k = $v;
                }
            }
            static::$flavors[$flavor] = $object;
            DISet($config['class'], new $config['class']);
        }

        return static::$flavors[$flavor];
    }
}
