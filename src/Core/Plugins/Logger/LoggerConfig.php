<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Yew\Core\Plugins\Config\BaseConfig;

class LoggerConfig extends BaseConfig
{
    const key = "yew.logger";
    /**
     * @var string
     */
    protected string $name = "log";

    /**
     * @var string
     */
    protected string $level = "debug";
    /**
     * @var string
     */
    protected string $output = "%datetime% \e[32m%level_name%\e[0m %user% %extra.about_process% %extra.class_and_func% : %message% %context% \n";
    /**
     * @var string|null
     */
    protected ?string $dateFormat = "Y-m-d H:i:s";
    /**
     * @var bool
     */
    protected bool $allowInlineLineBreaks = true;
    /**
     * @var bool
     */
    protected bool $ignoreEmptyContextAndExtra = true;

    /**
     * @var bool
     */
    protected bool $color = true;

    /**
     * @var int
     */
    protected int $maxFiles = 5;

    public function __construct()
    {
        parent::__construct(self::key);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * @param string $output
     */
    public function setOutput(string $output): void
    {
        $this->output = $output;
    }

    /**
     * @return string|null
     */
    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    /**
     * @param string|null $dateFormat
     */
    public function setDateFormat(?string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return bool
     */
    public function isAllowInlineLineBreaks(): bool
    {
        return $this->allowInlineLineBreaks;
    }

    /**
     * @param bool $allowInlineLineBreaks
     */
    public function setAllowInlineLineBreaks(bool $allowInlineLineBreaks): void
    {
        $this->allowInlineLineBreaks = $allowInlineLineBreaks;
    }

    /**
     * @return bool
     */
    public function isIgnoreEmptyContextAndExtra(): bool
    {
        return $this->ignoreEmptyContextAndExtra;
    }

    /**
     * @param bool $ignoreEmptyContextAndExtra
     */
    public function setIgnoreEmptyContextAndExtra(bool $ignoreEmptyContextAndExtra): void
    {
        $this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @param string $level
     */
    public function setLevel(string $level): void
    {
        $this->level = $level;
    }

    /**
     * @return bool
     */
    public function isColor(): bool
    {
        return $this->color;
    }

    /**
     * @param bool $color
     */
    public function setColor(bool $color): void
    {
        $this->color = $color;
    }

    /**
     * @return int
     */
    public function getMaxFiles(): int
    {
        return $this->maxFiles;
    }

    /**
     * @param int $maxFiles
     */
    public function setMaxFiles(int $maxFiles): void
    {
        $this->maxFiles = $maxFiles;
    }
}