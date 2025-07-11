<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yew Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Console;

use Yew\Framework\Console\Exception\Exception;
use Yew\Framework\Helpers\Console;
use Yew\Framework\Helpers\Inflector;
use Yew\Yew;
use Yew\Framework\Base\Action;
use Yew\Framework\Base\InlineAction;
use Yew\Framework\Exception\InvalidRouteException;

/**
 * Controller is the base class of console command classes.
 *
 * A console controller consists of one or several actions known as sub-commands.
 * Users call a console command by specifying the corresponding route which identifies a controller action.
 * The `yii` program is used when calling a console command, like the following:
 *
 * ```
 * yii <route> [--param1=value1 --param2 ...]
 * ```
 *
 * where `<route>` is a route to a controller action and the params will be populated as properties of a command.
 * See [[options()]] for details.
 *
 * @property string $help This property is read-only.
 * @property string $helpSummary This property is read-only.
 * @property array $passedOptionValues The properties corresponding to the passed options. This property is
 * read-only.
 * @property array $passedOptions The names of the options passed during execution. This property is
 * read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Controller extends \Yew\Framework\Base\Controller
{
    /**
     * @deprecated since 2.0.13. Use [[ExitCode::OK]] instead.
     */
    const EXIT_CODE_NORMAL = 0;
    /**
     * @deprecated since 2.0.13. Use [[ExitCode::UNSPECIFIED_ERROR]] instead.
     */
    const EXIT_CODE_ERROR = 1;

    /**
     * @var bool whether to run the command interactively.
     */
    public bool $interactive = true;
    /**
     * @var bool|null whether to enable ANSI color in the output.
     * If not set, ANSI color will only be enabled for terminals that support it.
     */
    public ?bool $color = null;
    /**
     * @var bool whether to display help information about current command.
     * @since 2.0.10
     */
    public bool $help = false;

    /**
     * @var array the options passed during execution.
     */
    private array $_passedOptions = [];

    /**
     * Returns a value indicating whether ANSI color is enabled.
     *
     * ANSI color is enabled only if [[color]] is set true or is not set
     * and the terminal supports ANSI color.
     *
     * @param resource $stream the stream to check.
     * @return bool Whether to enable ANSI style in output.
     */
    public function isColorEnabled($stream = \STDOUT): bool
    {
        return $this->color === null ? Console::streamSupportsAnsiColors($stream) : $this->color;
    }

    /**
     * Runs an action with the specified action ID and parameters.
     * If the action ID is empty, the method will use [[defaultAction]].
     * @param string $id the ID of the action to be executed.
     * @param array|null $params the parameters (name-value pairs) to be passed to the action.
     * @return int|null the status of the action execution. 0 means normal, other values mean abnormal.
     * @throws \Yew\Framework\Exception\Exception
     * @throws \Yew\Framework\Exception\InvalidConfigException
     * @throws \Yew\Framework\Exception\InvalidRouteException if the requested action ID cannot be resolved into an action successfully.
     * @throws \Yew\Framework\Console\Exception\Exception if there are unknown options or missing arguments
     * @throws \Yew\Framework\Console\Exception\UnknownCommandException
     * @throws \ReflectionException
     * @see createAction
     */
    public function runAction(string $id, ?array $params = []): ?int
    {
        if (!empty($params)) {
            // populate options here so that they are available in beforeAction().
            $options = $this->options($id === '' ? $this->defaultAction : $id);
            if (isset($params['_aliases'])) {
                $optionAliases = $this->optionAliases();
                foreach ($params['_aliases'] as $name => $value) {
                    if (array_key_exists($name, $optionAliases)) {
                        $params[$optionAliases[$name]] = $value;
                    } else {
                        $message = Yew::t('yew', 'Unknown alias: -{name}', ['name' => $name]);
                        if (!empty($optionAliases)) {
                            $aliasesAvailable = [];
                            foreach ($optionAliases as $alias => $option) {
                                $aliasesAvailable[] = '-' . $alias . ' (--' . $option . ')';
                            }

                            $message .= '. ' . Yew::t('yew', 'Aliases available: {aliases}', [
                                    'aliases' => implode(', ', $aliasesAvailable)
                                ]);
                        }
                        throw new Exception($message);
                    }
                }
                unset($params['_aliases']);
            }
            
            foreach ($params as $name => $value) {
                // Allow camelCase options to be entered in kebab-case
                if (!in_array($name, $options, true) && strpos($name, '-') !== false) {
                    $kebabName = $name;
                    $altName = lcfirst(Inflector::id2camel($kebabName));
                    if (in_array($altName, $options, true)) {
                        $name = $altName;
                    }
                }

                if (in_array($name, $options, true)) {
                    $default = $this->$name;
                    if (is_array($default)) {
                        $this->$name = preg_split('/\s*,\s*(?![^()]*\))/', $value);
                    } elseif ($default !== null) {
                        settype($value, gettype($default));
                        $this->$name = $value;
                    } else {
                        $this->$name = $value;
                    }
                    $this->_passedOptions[] = $name;
                    unset($params[$name]);
                    if (isset($kebabName)) {
                        unset($params[$kebabName]);
                    }
                } elseif (!is_int($name)) {
                    $message = Yew::t('yew', 'Unknown option: --{name}', ['name' => $name]);
                    if (!empty($options)) {
                        $message .= '. ' . Yew::t('yew', 'Options available: {options}', ['options' => '--' . implode(', --', $options)]);
                    }

                    throw new Exception($message);
                }
            }
        }
        if ($this->help) {
            $route = $this->getUniqueId() . '/' . $id;
            return Yew::$app->runAction('help', [$route]);
        }
        
        return parent::runAction($id, $params);
    }

    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * This method will first bind the parameters with the [[options()|options]]
     * available to the action. It then validates the given arguments.
     * @param Action $action the action to be bound with parameters
     * @param array|null $params the parameters to be bound to the action
     * @return array the valid parameters that the action can run with.
     * @throws \Yew\Framework\Console\Exception\Exception if there are unknown options or missing arguments
     * @throws \ReflectionException
     */
    public function bindActionParams(Action $action, ?array $params = null): array
    {
        if ($action instanceof InlineAction) {
            $method = new \ReflectionMethod($this, $action->actionMethod);
        } else {
            $method = new \ReflectionMethod($action, 'run');
        }

        $args = [];
        $missing = [];
        $actionParams = [];
        $requestedParams = [];

        foreach ($method->getParameters() as $i => $param) {
            $name = $param->getName();
            $key = null;
            if (array_key_exists($i, $params)) {
                $key = $i;
            } elseif (array_key_exists($name, $params)) {
                $key = $name;
            }

            if ($key !== null) {
                if ($param->isArray()) {
                    $params[$key] = $params[$key] === '' ? [] : preg_split('/\s*,\s*/', $params[$key]);
                }
                $args[] = $actionParams[$key] = $params[$key];
                unset($params[$key]);
            } elseif (
                PHP_VERSION_ID >= 70000 &&
                ($type = $param->getType()) !== null &&
                $type->isBuiltin() &&
                ((array_key_exists($name, $params)  && $params[$name] !== null) || !$type->allowsNull())
            ) {
                $typeName = PHP_VERSION_ID >= 70100 ? $type->getName() : (string)$type;
                switch ($typeName) {
                    case 'int':
                        $params[$name] = filter_var($params[$name], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                        break;
                    case 'float':
                        $params[$name] = filter_var($params[$name], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                        break;
                    case 'bool':
                        $params[$name] = filter_var($params[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        break;
                }
                if (array_key_exists($name, $params) && $params[$name] === null) {
                    $isValid = false;
                }
            } elseif (PHP_VERSION_ID >= 70100 &&
                ($type = $param->getType()) !== null &&
                !$type->isBuiltin()) {
                try {
                    $this->bindInjectedParams($type, $name, $args, $requestedParams);
                } catch (\Yew\Framework\Exception\Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$i] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new Exception(Yew::t('yew', 'Missing required arguments: {params}', ['params' => implode(', ', $missing)]));
        }

        return $args;
    }

    /**
     * Formats a string with ANSI codes.
     *
     * You may pass additional parameters using the constants defined in [[\Yew\Yew\Helpers\Console]].
     *
     * Example:
     *
     * ```
     * echo $this->ansiFormat('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to be formatted
     * @return string
     */
    public function ansiFormat(string $string): string
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return $string;
    }

    /**
     * Prints a string to STDOUT.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\Yew\Yew\Helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stdout('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @param int ...$args additional parameters to decorate the output
     * @return int|bool Number of bytes printed or false on error
     */
    public function stdout(string $string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return Console::stdout($string);
    }

    /**
     * Prints a string to STDERR.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\Yew\Yew\Helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stderr('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public function stderr(string $string)
    {
        if ($this->isColorEnabled(\STDERR)) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return fwrite(\STDERR, $string);
    }

    /**
     * Prompts the user for input and validates it.
     *
     * @param string $text prompt string
     * @param array $options the options to validate the input:
     *
     *  - required: whether it is required or not
     *  - default: default value if no input is inserted by the user
     *  - pattern: regular expression pattern to validate user input
     *  - validator: a callable function to validate input. The function must accept two parameters:
     *      - $input: the user input to validate
     *      - $error: the error value passed by reference if validation failed.
     *
     * An example of how to use the prompt method with a validator function.
     *
     * ```php
     * $code = $this->prompt('Enter 4-Chars-Pin', ['required' => true, 'validator' => function($input, &$error) {
     *     if (strlen($input) !== 4) {
     *         $error = 'The Pin must be exactly 4 chars!';
     *         return false;
     *     }
     *     return true;
     * }]);
     * ```
     *
     * @return string the user input
     */
    public function prompt(string $text, ?array $options = []): string
    {
        if ($this->interactive) {
            return Console::prompt($text, $options);
        }

        return $options['default'] ?? '';
    }

    /**
     * Asks user to confirm by typing y or n.
     *
     * A typical usage looks like the following:
     *
     * ```php
     * if ($this->confirm("Are you sure?")) {
     *     echo "user typed yes\n";
     * } else {
     *     echo "user typed no\n";
     * }
     * ```
     *
     * @param string $message to echo out before waiting for user input
     * @param bool $default this value is returned if no selection is made.
     * @return bool whether user confirmed.
     * Will return true if [[interactive]] is false.
     */
    public function confirm(string $message, ?bool $default = false): bool
    {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        }

        return true;
    }

    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array|null $options Key-value array of options to choose from
     *
     * @return string An option character the user chose
     */
    public function select(string $prompt, ?array $options = []): string
    {
        return Console::select($prompt, $options);
    }

    /**
     * Returns the names of valid options for the action (id)
     * An option requires the existence of a public member variable whose
     * name is the option name.
     * Child classes may override this method to specify possible options.
     *
     * Note that the values setting via options are not available
     * until [[beforeAction()]] is being called.
     *
     * @param string $actionID the action id of the current request
     * @return string[] the names of the options valid for the action
     */
    public function options(string $actionID): array
    {
        // $actionId might be used in subclasses to provide options specific to action id
        return ['color', 'interactive', 'help', 'silentExitOnException', 'command', 'route'];
    }

    /**
     * Returns option alias names.
     * Child classes may override this method to specify alias options.
     *
     * @return array the options alias names valid for the action
     * where the keys is alias name for option and value is option name.
     *
     * @since 2.0.8
     * @see options()
     */
    public function optionAliases(): array
    {
        return [
            'h' => 'help',
        ];
    }

    /**
     * Returns properties corresponding to the options for the action id
     * Child classes may override this method to specify possible properties.
     *
     * @param string $actionID the action id of the current request
     * @return array properties corresponding to the options for the action
     */
    public function getOptionValues(string $actionID): array
    {
        // $actionId might be used in subclasses to provide properties specific to action id
        $properties = [];
        foreach ($this->options($this->action->id) as $property) {
            $properties[$property] = $this->$property;
        }

        return $properties;
    }

    /**
     * Returns the names of valid options passed during execution.
     *
     * @return array the names of the options passed during execution
     */
    public function getPassedOptions()
    {
        return $this->_passedOptions;
    }

    /**
     * Returns the properties corresponding to the passed options.
     *
     * @return array the properties corresponding to the passed options
     */
    public function getPassedOptionValues(): array
    {
        $properties = [];
        foreach ($this->_passedOptions as $property) {
            $properties[$property] = $this->$property;
        }

        return $properties;
    }

    /**
     * Returns one-line short summary describing this controller.
     *
     * You may override this method to return customized summary.
     * The default implementation returns first line from the PHPDoc comment.
     *
     * @return string
     */
    public function getHelpSummary(): string
    {
        return $this->parseDocCommentSummary(new \ReflectionClass($this));
    }

    /**
     * Returns help information for this controller.
     *
     * You may override this method to return customized help.
     * The default implementation returns help information retrieved from the PHPDoc comment.
     * @return string
     */
    public function getHelp(): string
    {
        return $this->parseDocCommentDetail(new \ReflectionClass($this));
    }

    /**
     * Returns a one-line short summary describing the specified action.
     * @param Action $action action to get summary for
     * @return string a one-line short summary describing the specified action.
     */
    public function getActionHelpSummary($action): string
    {
        if ($action === null) {
            return $this->ansiFormat(Yew::t('yew', 'Action not found.'), Console::FG_RED);
        }

        return $this->parseDocCommentSummary($this->getActionMethodReflection($action));
    }

    /**
     * Returns the detailed help information for the specified action.
     * @param Action $action action to get help for
     * @return string the detailed help information for the specified action.
     */
    public function getActionHelp(Action $action): string
    {
        return $this->parseDocCommentDetail($this->getActionMethodReflection($action));
    }

    /**
     * Returns the help information for the anonymous arguments for the action.
     *
     * The returned value should be an array. The keys are the argument names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - required: boolean, whether this argument is required.
     * - type: string, the PHP type of this argument.
     * - default: string, the default value of this argument
     * - comment: string, the comment of this argument
     *
     * The default implementation will return the help information extracted from the doc-comment of
     * the parameters corresponding to the action method.
     *
     * @param Action $action
     * @return array the help information of the action arguments
     */
    public function getActionArgsHelp(Action $action): array
    {
        $method = $this->getActionMethodReflection($action);
        $tags = $this->parseDocCommentTags($method);
        $params = isset($tags['param']) ? (array)$tags['param'] : [];

        $args = [];

        /** @var \ReflectionParameter $reflection */
        foreach ($method->getParameters() as $i => $reflection) {
            if ($reflection->getClass() !== null) {
                continue;
            }
            $name = $reflection->getName();
            $tag = isset($params[$i]) ? $params[$i] : '';
            if (preg_match('/^(\S+)\s+(\$\w+\s+)?(.*)/s', $tag, $matches)) {
                $type = $matches[1];
                $comment = $matches[3];
            } else {
                $type = null;
                $comment = $tag;
            }
            if ($reflection->isDefaultValueAvailable()) {
                $args[$name] = [
                    'required' => false,
                    'type' => $type,
                    'default' => $reflection->getDefaultValue(),
                    'comment' => $comment,
                ];
            } else {
                $args[$name] = [
                    'required' => true,
                    'type' => $type,
                    'default' => null,
                    'comment' => $comment,
                ];
            }
        }

        return $args;
    }

    /**
     * Returns the help information for the options for the action.
     *
     * The returned value should be an array. The keys are the option names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - type: string, the PHP type of this argument.
     * - default: string, the default value of this argument
     * - comment: string, the comment of this argument
     *
     * The default implementation will return the help information extracted from the doc-comment of
     * the properties corresponding to the action options.
     *
     * @param Action $action
     * @return array the help information of the action options
     */
    public function getActionOptionsHelp(Action $action): array
    {
        $optionNames = $this->options($action->id);
        if (empty($optionNames)) {
            return [];
        }

        $class = new \ReflectionClass($this);
        $options = [];
        foreach ($class->getProperties() as $property) {
            $name = $property->getName();
            if (!in_array($name, $optionNames, true)) {
                continue;
            }
            $defaultValue = $property->getValue($this);
            $tags = $this->parseDocCommentTags($property);

            // Display camelCase options in kebab-case
            $name = Inflector::camel2id($name, '-', true);

            if (isset($tags['var']) || isset($tags['property'])) {
                $doc = isset($tags['var']) ? $tags['var'] : $tags['property'];
                if (is_array($doc)) {
                    $doc = reset($doc);
                }
                if (preg_match('/^(\S+)(.*)/s', $doc, $matches)) {
                    $type = $matches[1];
                    $comment = $matches[2];
                } else {
                    $type = null;
                    $comment = $doc;
                }
                $options[$name] = [
                    'type' => $type,
                    'default' => $defaultValue,
                    'comment' => $comment,
                ];
            } else {
                $options[$name] = [
                    'type' => null,
                    'default' => $defaultValue,
                    'comment' => '',
                ];
            }
        }

        return $options;
    }

    private $_reflections = [];

    /**
     * @param Action $action
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    protected function getActionMethodReflection(Action $action): \ReflectionMethod
    {
        if (!isset($this->_reflections[$action->id])) {
            if ($action instanceof InlineAction) {
                $this->_reflections[$action->id] = new \ReflectionMethod($this, $action->actionMethod);
            } else {
                $this->_reflections[$action->id] = new \ReflectionMethod($action, 'run');
            }
        }

        return $this->_reflections[$action->id];
    }

    /**
     * Parses the comment block into tags.
     * @param \Reflector $reflection the comment block
     * @return array the parsed tags
     */
    protected function parseDocCommentTags(\Reflector $reflection): array
    {
        $comment = $reflection->getDocComment();
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/ms', trim($part), $matches)) {
                $name = $matches[1];
                if (!isset($tags[$name])) {
                    $tags[$name] = trim($matches[2]);
                } elseif (is_array($tags[$name])) {
                    $tags[$name][] = trim($matches[2]);
                } else {
                    $tags[$name] = [$tags[$name], trim($matches[2])];
                }
            }
        }

        return $tags;
    }

    /**
     * Returns the first line of docblock.
     *
     * @param \Reflector $reflection
     * @return string
     */
    protected function parseDocCommentSummary(\Reflector $reflection): string
    {
        $docLines = preg_split('~\R~u', $reflection->getDocComment());
        if (isset($docLines[1])) {
            return trim($docLines[1], "\t *");
        }

        return '';
    }

    /**
     * Returns full description from the docblock.
     *
     * @param \Reflector $reflection
     * @return string
     */
    protected function parseDocCommentDetail(\Reflector $reflection): string
    {
        $comment = strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($reflection->getDocComment(), '/'))), "\r", '');
        if (preg_match('/^\s*@\w+/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            $comment = trim(substr($comment, 0, $matches[0][1]));
        }
        if ($comment !== '') {
            return rtrim(Console::renderColoredString(Console::markdownToAnsi($comment)));
        }

        return '';
    }
}
