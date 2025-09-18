<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Database;

class Config extends \Yew\Core\Pool\Config
{
    /**
     * @var string
     */
    protected string $dsn = "";

    /**
     * @var string
     */
    protected string $username = "";

    /**
     * @var string
     */
    protected string $password = "";

    /**
     * @var string
     */
    protected string $tablePrefix = "";

    /**
     * @var string
     */
    protected string $charset = "utf8";

    /**
     * @var bool
     */
    protected bool $enableSchemaCache = false;

    /**
     * @var int
     */
    protected int $schemaCacheDuration = 0;

    /**
     * @var string
     */
    protected string $schemaCache = "cache";

    /**
     * @return string
     */
    protected function getKey(): string
    {
        return 'database';
    }

    /**
     * @return string
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * @param string $tablePrefix
     */
    public function setTablePrefix(string $tablePrefix): void
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }

    /**
     * @return string
     */
    public function getEnableSchemaCache(): string
    {
        return $this->enableSchemaCache;
    }

    /**
     * @param bool|int|mixed $enable
     */
    public function setEnableSchemaCache($enable): void
    {
        $this->enableSchemaCache = (bool)$enable;
    }

    /**
     * @return int
     */
    public function getSchemaCacheDuration(): int
    {
        return $this->schemaCacheDuration;
    }

    /**
     * @param int $duration
     */
    public function setSchemaCacheDuration(int $duration): void
    {
        $this->schemaCacheDuration = $duration;
    }

    /**
     * @return string
     */
    public function getSchemaCache(): string
    {
        return $this->schemaCache;
    }

    /**
     * @param string $cache
     */
    public function setSchemaCache(string $cache): void
    {
        if (!empty($cache)) {
            $this->schemaCache = $cache;
        }
    }

    /**
     * Build config
     * @return array
     */
    public function buildConfig(): array
    {
        return [
            'dsn' => $this->getDsn(),
            'name' => $this->getName(),
            'username' => $this->getUsername(),
            'password' => $this->getPassword(),
            'tablePrefix' => $this->getTablePrefix(),
            'charset' => $this->getCharset(),
            'enableSchemaCache' => $this->getEnableSchemaCache(),
            'schemaCacheDuration' => $this->getSchemaCacheDuration(),
            'schemaCache' => $this->getSchemaCache(),
            'options' => $this->getOptions()
        ];
    }

    /**
     * Returns the name of the DB driver. Based on the current [[dsn]], in case it was not set explicitly
     * by an end user.
     * @return string name of the DB driver
     */
    public function getDriverName(): string
    {
        if (($pos = strpos($this->dsn, ':')) !== false) {
            return strtolower(substr($this->dsn, 0, $pos));
        }
        return "";
    }
}
