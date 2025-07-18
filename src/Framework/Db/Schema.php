<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

use Yew\Yew;
use Yew\Framework\Base\BaseObject;
use Yew\Framework\Exception\InvalidCallException;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\NotSupportedException;
use Yew\Framework\Caching\Cache;
use Yew\Framework\Caching\CacheInterface;
use Yew\Framework\Caching\TagDependency;

/**
 * Schema is the base class for concrete DBMS-specific schema classes.
 *
 * Schema represents the database schema information that is DBMS specific.
 *
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the
 * sequence object. This property is read-only.
 * @property QueryBuilder $queryBuilder The query builder for this connection. This property is read-only.
 * @property string[] $schemaNames All schema names in the database, except system schemas. This property is
 * read-only.
 * @property string $serverVersion Server version as a string. This property is read-only.
 * @property string[] $tableNames All table names in the database. This property is read-only.
 * @property TableSchema[] $tableSchemas The metadata for all tables in the database. Each array element is an
 * instance of [[TableSchema]] or its child class. This property is read-only.
 * @property string $transactionIsolationLevel The transaction isolation level to use for this transaction.
 * This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]],
 * [[Transaction::REPEATABLE_READ]] and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific
 * syntax to be used after `SET TRANSACTION ISOLATION LEVEL`. This property is write-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0
 */
abstract class Schema extends BaseObject
{
    // The following are the supported abstract column data types.
    const TYPE_PK = 'pk';
    const TYPE_UPK = 'upk';
    const TYPE_BIGPK = 'bigpk';
    const TYPE_UBIGPK = 'ubigpk';
    const TYPE_CHAR = 'char';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_TINYINT = 'tinyint';
    const TYPE_SMALLINT = 'smallint';
    const TYPE_INTEGER = 'integer';
    const TYPE_BIGINT = 'bigint';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_DATETIME = 'datetime';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIME = 'time';
    const TYPE_DATE = 'date';
    const TYPE_BINARY = 'binary';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_MONEY = 'money';
    const TYPE_JSON = 'json';
    /**
     * Schema cache version, to detect incompatibilities in cached values when the
     * data format of the cache changes.
     */
    const SCHEMA_CACHE_VERSION = 1;

    /**
     * @var Connection the database connection
     */
    public Connection $db;
    /**
     * @var string the default schema name used for the current session.
     */
    public ?string $defaultSchema = null;
    /**
     * @var array map of DB errors and corresponding exceptions
     * If left part is found in DB error message exception class from the right part is used.
     */
    public array $exceptionMap = [
        'SQLSTATE[23' => 'Yew\Framework\Db\IntegrityException',
    ];
    /**
     * @var string|array column schema class or class config
     * @since 2.0.11
     */
    public $columnSchemaClass = 'Yew\Framework\Db\ColumnSchema';

    /**
     * @var string|string[] character used to quote schema, table, etc. names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     * @since 2.0.14
     */
    protected $tableQuoteCharacter = "'";
    /**
     * @var string|string[] character used to quote column names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     * @since 2.0.14
     */
    protected $columnQuoteCharacter = '"';

    /**
     * @var array|null list of ALL schema names in the database, except system schemas
     */
    private ?array $_schemaNames = null;

    /**
     * @var array list of ALL table names in the database
     */
    private array $_tableNames = [];

    /**
     * @var array list of loaded table metadata (table name => metadata type => metadata).
     */
    private array $_tableMetadata = [];

    /**
     * @var QueryBuilder|null the query builder for this database
     */
    private ?QueryBuilder $_builder = null;

    /**
     * @var string|null server version as a string.
     */
    private ?string $_serverVersion = null;


    /**
     * Resolves the table name and schema name (if any).
     * @param string $name the table name
     * @return TableSchema [[TableSchema]] with resolved table, schema, etc. names.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     * @since 2.0.13
     */
    protected function resolveTableName(string $name): TableSchema
    {
        throw new NotSupportedException(get_class($this) . ' does not support resolving table names.');
    }

    /**
     * Returns all schema names in the database, including the default one but not system schemas.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @return array all schema names in the database, except system schemas.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     * @since 2.0.4
     */
    protected function findSchemaNames(): array
    {
        throw new NotSupportedException(get_class($this) . ' does not support fetching all schema names.');
    }

    /**
     * Returns all table names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     */
    protected function findTableNames(string $schema = ''): array
    {
        throw new NotSupportedException(get_class($this) . ' does not support fetching all table names.');
    }

    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema|null DBMS-dependent table metadata, `null` if the table does not exist.
     */
    abstract protected function loadTableSchema(string $name): ?TableSchema;

    /**
     * Creates a column schema for the database.
     * This method may be overridden by child classes to create a DBMS-specific column schema.
     * @return ColumnSchema column schema instance.
     * @throws InvalidConfigException if a column schema class cannot be created.
     */
    protected function createColumnSchema()
    {
        return Yew::createObject($this->columnSchemaClass);
    }

    /**
     * Obtains the metadata for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema|null table metadata. `null` if the named table does not exist.
     * @throws InvalidConfigException
     */
    public function getTableSchema(string $name, bool $refresh = false): ?TableSchema
    {
        return $this->getTableMetadata($name, 'schema', $refresh);
    }

    /**
     * Returns the metadata for all tables in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * @param bool $refresh whether to fetch the latest available table schemas. If this is `false`,
     * cached data may be returned if available.
     * @return TableSchema[] the metadata for all tables in the database.
     * Each array element is an instance of [[TableSchema]] or its child class.
     * @throws NotSupportedException
     */
    public function getTableSchemas(string $schema = '', bool $refresh = false)
    {
        return $this->getSchemaMetadata($schema, 'schema', $refresh);
    }

    /**
     * Returns all schema names in the database, except system schemas.
     * @param bool $refresh whether to fetch the latest available schema names. If this is false,
     * schema names fetched previously (if available) will be returned.
     * @return string[] all schema names in the database, except system schemas.
     * @throws NotSupportedException
     * @since 2.0.4
     */
    public function getSchemaNames(bool $refresh = false)
    {
        if ($this->_schemaNames === null || $refresh) {
            $this->_schemaNames = $this->findSchemaNames();
        }

        return $this->_schemaNames;
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * If not empty, the returned table names will be prefixed with the schema name.
     * @param bool $refresh whether to fetch the latest available table names. If this is false,
     * table names fetched previously (if available) will be returned.
     * @return string[] all table names in the database.
     * @throws NotSupportedException
     */
    public function getTableNames(string $schema = '', bool $refresh = false): array
    {
        if (!isset($this->_tableNames[$schema]) || $refresh) {
            $this->_tableNames[$schema] = $this->findTableNames($schema);
        }

        return $this->_tableNames[$schema];
    }

    /**
     * @return QueryBuilder the query builder for this connection.
     */
    public function getQueryBuilder(): QueryBuilder
    {
        if ($this->_builder === null) {
            $this->_builder = $this->createQueryBuilder();
        }

        return $this->_builder;
    }

    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return int the PDO type
     * @see https://secure.php.net/manual/en/pdo.constants.php
     */
    public function getPdoType($data): int
    {
        static $typeMap = [
            // php type => PDO type
            'boolean' => \PDO::PARAM_BOOL,
            'integer' => \PDO::PARAM_INT,
            'string' => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL' => \PDO::PARAM_NULL,
        ];
        $type = gettype($data);

        return $typeMap[$type] ?? \PDO::PARAM_STR;
    }

    /**
     * Refreshes the schema.
     * This method cleans up all cached table schemas so that they can be re-created later
     * to reflect the database schema change.
     */
    public function refresh()
    {
        /* @var $cache CacheInterface */
        $cache = is_string($this->db->schemaCache) ? Yew::$app->get($this->db->schemaCache, false) : $this->db->schemaCache;
        if ($this->db->enableSchemaCache && $cache instanceof CacheInterface) {
            TagDependency::invalidate($cache, $this->getCacheTag());
        }
        $this->_tableNames = [];
        $this->_tableMetadata = [];
    }

    /**
     * Refreshes the particular table schema.
     * This method cleans up cached table schema so that it can be re-created later
     * to reflect the database schema change.
     * @param string $name table name.
     * @throws InvalidConfigException
     * @since 2.0.6
     */
    public function refreshTableSchema(string $name)
    {
        $rawName = $this->getRawTableName($name);
        unset($this->_tableMetadata[$rawName]);
        $this->_tableNames = [];
        /* @var $cache CacheInterface */
        $cache = is_string($this->db->schemaCache) ? Yew::$app->get($this->db->schemaCache, false) : $this->db->schemaCache;
        if ($this->db->enableSchemaCache && $cache instanceof CacheInterface) {
            $cache->delete($this->getCacheKey($rawName));
        }
    }

    /**
     * Creates a query builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     * @return QueryBuilder query builder instance
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->db);
    }

    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema builder.
     *
     * @param string $type type of the column. See [[ColumnSchemaBuilder::$type]].
     * @param int|string|array $length length or precision of the column. See [[ColumnSchemaBuilder::$length]].
     * @return ColumnSchemaBuilder column schema builder instance
     * @since 2.0.6
     */
    public function createColumnSchemaBuilder(string $type, $length = null): ColumnSchemaBuilder
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    /**
     * Returns all unique indexes for the given table.
     *
     * Each array element is of the following structure:
     *
     * ```php
     * [
     *  'IndexName1' => ['col1' [, ...]],
     *  'IndexName2' => ['col2' [, ...]],
     * ]
     * ```
     *
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception
     * @param TableSchema $table the table metadata
     * @return array all unique indexes for the given table.
     * @throws NotSupportedException if this method is called
     */
    public function findUniqueIndexes(TableSchema $table): array
    {
        throw new NotSupportedException(get_class($this) . ' does not support getting unique indexes information.');
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @throws InvalidCallException if the DB connection is not active
     * @see https://secure.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID(string $sequenceName = ''): string
    {
        if ($this->db->isActive) {
            return $this->db->pdo->lastInsertId($sequenceName === '' ? null : $this->quoteTableName($sequenceName));
        }

        throw new InvalidCallException('DB Connection is not active.');
    }

    /**
     * @return bool whether this DBMS supports [savepoint](http://en.wikipedia.org/wiki/Savepoint).
     */
    public function supportsSavepoint(): bool
    {
        return $this->db->enableSavepoint;
    }

    /**
     * Creates a new savepoint.
     * @param string $name the savepoint name
     * @throws Exception
     */
    public function createSavepoint(string $name)
    {
        $this->db->createCommand("SAVEPOINT $name")->execute();
    }

    /**
     * Releases an existing savepoint.
     * @param string $name the savepoint name
     * @throws Exception
     */
    public function releaseSavepoint(string $name)
    {
        $this->db->createCommand("RELEASE SAVEPOINT $name")->execute();
    }

    /**
     * Rolls back to a previously created savepoint.
     * @param string $name the savepoint name
     * @throws Exception
     */
    public function rollBackSavepoint(string $name)
    {
        $this->db->createCommand("ROLLBACK TO SAVEPOINT $name")->execute();
    }

    /**
     * Sets the isolation level of the current transaction.
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]], [[Transaction::REPEATABLE_READ]]
     * and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific syntax to be used
     * after `SET TRANSACTION ISOLATION LEVEL`.
     * @throws Exception
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    public function setTransactionIsolationLevel(string $level)
    {
        $this->db->createCommand("SET TRANSACTION ISOLATION LEVEL $level")->execute();
    }

    /**
     * Executes the INSERT command, returning primary key values.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     * @return array|false primary key values or false if the command fails
     * @throws Exception|InvalidConfigException
     * @since 2.0.4
     */
    public function insert(string $table, array $columns)
    {
        $command = $this->db->createCommand()->insert($table, $columns);
        if (!$command->execute()) {
            return false;
        }
        $tableSchema = $this->getTableSchema($table);
        $result = [];
        foreach ($tableSchema->primaryKey as $name) {
            if ($tableSchema->columns[$name]->autoIncrement) {
                $result[$name] = $this->getLastInsertID($tableSchema->sequenceName);
                break;
            }

            $result[$name] = $columns[$name] ?? $tableSchema->columns[$name]->defaultValue;
        }

        return $result;
    }

    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $str string to be quoted
     * @return string the properly quoted string
     * @see https://secure.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue(string $str): string
    {
        if (($value = $this->db->getSlavePdo()->quote($str)) !== false) {
            return $value;
        }

        // the driver doesn't support quote (e.g. oci)
        return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains '(' or '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     * @see quoteSimpleTableName()
     */
    public function quoteTableName(string $name): string
    {
        if (strpos($name, '(') !== false || strpos($name, '{{') !== false) {
            return $name;
        }
        if (strpos($name, '.') === false) {
            return $this->quoteSimpleTableName($name);
        }
        $parts = explode('.', $name);
        foreach ($parts as $i => $part) {
            $parts[$i] = $this->quoteSimpleTableName($part);
        }

        return implode('.', $parts);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains '(', '[[' or '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     * @see quoteSimpleColumnName()
     */
    public function quoteColumnName(string $name): string
    {
        if (strpos($name, '(') !== false || strpos($name, '[[') !== false) {
            return $name;
        }
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quoteTableName(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }
        if (strpos($name, '{{') !== false) {
            return $name;
        }

        return $prefix . $this->quoteSimpleColumnName($name);
    }

    /**
     * Quotes a simple table name for use in a query.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is already quoted, this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteSimpleTableName(string $name): string
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $endingCharacter = $this->tableQuoteCharacter;
        } else {
            list($startingCharacter, $endingCharacter) = $this->tableQuoteCharacter;
        }
        return strpos($name, $startingCharacter) !== false ? $name : $startingCharacter . $name . $endingCharacter;
    }

    /**
     * Quotes a simple column name for use in a query.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is already quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName(string $name): string
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $endingCharacter = $this->columnQuoteCharacter;
        } else {
            list($startingCharacter, $endingCharacter) = $this->columnQuoteCharacter;
        }
        return $name === '*' || strpos($name, $startingCharacter) !== false ? $name : $startingCharacter . $name . $endingCharacter;
    }

    /**
     * Unquotes a simple table name.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is not quoted, this method will do nothing.
     * @param string $name table name.
     * @return string unquoted table name.
     * @since 2.0.14
     */
    public function unquoteSimpleTableName(string $name): string
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $this->tableQuoteCharacter;
        } else {
            $startingCharacter = $this->tableQuoteCharacter[0];
        }
        return strpos($name, $startingCharacter) === false ? $name : substr($name, 1, -1);
    }

    /**
     * Unquotes a simple column name.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is not quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name.
     * @return string unquoted column name.
     * @since 2.0.14
     */
    public function unquoteSimpleColumnName(string $name): string
    {
        if (is_string($this->columnQuoteCharacter)) {
            $startingCharacter = $this->columnQuoteCharacter;
        } else {
            $startingCharacter = $this->columnQuoteCharacter[0];
        }
        return strpos($name, $startingCharacter) === false ? $name : substr($name, 1, -1);
    }

    /**
     * Returns the actual name of a given table name.
     * This method will strip off curly brackets from the given table name
     * and replace the percentage character '%' with [[Connection::tablePrefix]].
     * @param string $name the table name to be converted
     * @return string the real name of the given table name
     */
    public function getRawTableName(string $name): string
    {
        if (strpos($name, '{{') !== false) {
            $name = preg_replace('/\\{\\{(.*?)\\}\\}/', '\1', $name);

            return str_replace('%', $this->db->tablePrefix, $name);
        }

        return $name;
    }

    /**
     * Extracts the PHP type from abstract DB type.
     * @param ColumnSchema $column the column schema information
     * @return string PHP type name
     */
    protected function getColumnPhpType(ColumnSchema $column): string
    {
        static $typeMap = [
            // abstract type => php type
            self::TYPE_TINYINT => 'integer',
            self::TYPE_SMALLINT => 'integer',
            self::TYPE_INTEGER => 'integer',
            self::TYPE_BIGINT => 'integer',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_FLOAT => 'double',
            self::TYPE_DOUBLE => 'double',
            self::TYPE_BINARY => 'resource',
            self::TYPE_JSON => 'array',
        ];
        if (isset($typeMap[$column->type])) {
            if ($column->type === 'bigint') {
                return PHP_INT_SIZE === 8 && !$column->unsigned ? 'integer' : 'string';
            } elseif ($column->type === 'integer') {
                return PHP_INT_SIZE === 4 && $column->unsigned ? 'string' : 'integer';
            }

            return $typeMap[$column->type];
        }

        return 'string';
    }

    /**
     * Converts a DB exception to a more concrete one if possible.
     *
     * @param \Exception $e
     * @param string $rawSql SQL that produced exception
     * @return Exception
     */
    public function convertException(\Exception $e, string $rawSql): Exception
    {
        if ($e instanceof Exception) {
            return $e;
        }

        $exceptionClass = '\Yew\Framework\Db\Exception';
        foreach ($this->exceptionMap as $error => $class) {
            if (strpos($e->getMessage(), $error) !== false) {
                $exceptionClass = $class;
            }
        }

        $message = $e->getMessage() . "\nThe SQL being executed was: $rawSql";
        $errorInfo = $e instanceof \PDOException ? $e->errorInfo : null;
        return new $exceptionClass($message, $errorInfo, (int) $e->getCode(), $e);
    }

    /**
     * Returns a value indicating whether a SQL statement is for read purpose.
     * @param string $sql the SQL statement
     * @return bool whether a SQL statement is for read purpose.
     */
    public function isReadQuery(string $sql): bool
    {
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE)\b/i';
        return preg_match($pattern, $sql) > 0;
    }

    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     * @since 2.0.14
     */
    public function getServerVersion(): ?string
    {
        if ($this->_serverVersion === null) {
            $this->_serverVersion = $this->db->getSlavePdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        }
        return $this->_serverVersion;
    }

    /**
     * Returns the cache key for the specified table name.
     * @param string $name the table name.
     * @return array the cache key.
     */
    protected function getCacheKey(string $name): array
    {
        return [
            __CLASS__,
            $this->db->dsn,
            $this->db->username,
            $this->getRawTableName($name),
        ];
    }

    /**
     * Returns the cache tag name.
     * This allows [[refresh()]] to invalidate all cached table schemas.
     * @return string the cache tag name
     */
    protected function getCacheTag(): string
    {
        return md5(serialize([
            __CLASS__,
            $this->db->dsn,
            $this->db->username,
        ]));
    }

    /**
     * Returns the metadata of the given type for the given table.
     * If there's no metadata in the cache, this method will call
     * a `'loadTable' . ucfirst($type)` named method with the table name to obtain the metadata.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param string $type metadata type.
     * @param bool $refresh whether to reload the table metadata even if it is found in the cache.
     * @return mixed metadata.
     * @throws InvalidConfigException
     * @since 2.0.13
     */
    protected function getTableMetadata(string $name, string $type, bool $refresh)
    {
        $cache = null;
        if ($this->db->enableSchemaCache && !in_array($name, $this->db->schemaCacheExclude, true)) {
            $schemaCache = is_string($this->db->schemaCache) ? Yew::$app->get($this->db->schemaCache, false) : $this->db->schemaCache;
            if ($schemaCache instanceof CacheInterface) {
                $cache = $schemaCache;
            }
        }
        $rawName = $this->getRawTableName($name);
        if (!isset($this->_tableMetadata[$rawName])) {
            $this->loadTableMetadataFromCache($cache, $rawName);
        }
        if ($refresh || !array_key_exists($type, $this->_tableMetadata[$rawName])) {
            $this->_tableMetadata[$rawName][$type] = $this->{'loadTable' . ucfirst($type)}($rawName);
            $this->saveTableMetadataToCache($cache, $rawName);
        }

        return $this->_tableMetadata[$rawName][$type];
    }

    /**
     * Returns the metadata of the given type for all tables in the given schema.
     * This method will call a `'getTable' . ucfirst($type)` named method with the table name
     * and the refresh flag to obtain the metadata.
     * @param string $schema the schema of the metadata. Defaults to empty string, meaning the current or default schema name.
     * @param string $type metadata type.
     * @param bool $refresh whether to fetch the latest available table metadata. If this is `false`,
     * cached data may be returned if available.
     * @return array array of metadata.
     * @throws NotSupportedException
     * @since 2.0.13
     */
    protected function getSchemaMetadata(string $schema, string $type, bool $refresh): array
    {
        $metadata = [];
        $methodName = 'getTable' . ucfirst($type);
        foreach ($this->getTableNames($schema, $refresh) as $name) {
            if ($schema !== '') {
                $name = $schema . '.' . $name;
            }
            $tableMetadata = $this->$methodName($name, $refresh);
            if ($tableMetadata !== null) {
                $metadata[] = $tableMetadata;
            }
        }

        return $metadata;
    }

    /**
     * Sets the metadata of the given type for the given table.
     * @param string $name table name.
     * @param string $type metadata type.
     * @param mixed $data metadata.
     * @since 2.0.13
     */
    protected function setTableMetadata(string $name, string $type, $data)
    {
        $this->_tableMetadata[$this->getRawTableName($name)][$type] = $data;
    }

    /**
     * Changes row's array key case to lower if PDO's one is set to uppercase.
     * @param array $row row's array or an array of row's arrays.
     * @param bool $multiple whether multiple rows or a single row passed.
     * @return array normalized row or rows.
     * @since 2.0.13
     */
    protected function normalizePdoRowKeyCase(array $row, bool $multiple): array
    {
        if ($this->db->getSlavePdo()->getAttribute(\PDO::ATTR_CASE) !== \PDO::CASE_UPPER) {
            return $row;
        }

        if ($multiple) {
            return array_map(function (array $row) {
                return array_change_key_case($row, CASE_LOWER);
            }, $row);
        }

        return array_change_key_case($row, CASE_LOWER);
    }

    /**
     * Tries to load and populate table metadata from cache.
     * @param Cache|null $cache
     * @param string $name
     */
    private function loadTableMetadataFromCache(?Cache $cache, string $name)
    {
        if ($cache === null) {
            $this->_tableMetadata[$name] = [];
            return;
        }

        $metadata = $cache->get($this->getCacheKey($name));
        if (!is_array($metadata) || !isset($metadata['cacheVersion']) || $metadata['cacheVersion'] !== static::SCHEMA_CACHE_VERSION) {
            $this->_tableMetadata[$name] = [];
            return;
        }

        unset($metadata['cacheVersion']);
        $this->_tableMetadata[$name] = $metadata;
    }

    /**
     * Saves table metadata to cache.
     * @param Cache|null $cache
     * @param string $name
     */
    private function saveTableMetadataToCache(?Cache $cache, string $name)
    {
        if ($cache === null) {
            return;
        }

        $metadata = $this->_tableMetadata[$name];
        $metadata['cacheVersion'] = static::SCHEMA_CACHE_VERSION;
        $cache->set(
            $this->getCacheKey($name),
            $metadata,
            $this->db->schemaCacheDuration,
            new TagDependency(['tags' => $this->getCacheTag()])
        );
    }
}
