<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

use Yew\Coroutine\Server\Server;
use Yew\Framework\Caching\CacheInterface;
use Yew\Framework\Caching\Dependency;
use Yew\Framework\Base\Component;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\NotSupportedException;
use Yew\Yew;

/**
 * Command represents a SQL statement to be executed against a database.
 *
 * A command object is usually created by calling [[Connection::createCommand()]].
 * The SQL statement it represents can be set via the [[sql]] property.
 *
 * To execute a non-query SQL (such as INSERT, DELETE, UPDATE), call [[execute()]].
 * To execute a SQL statement that returns a result data set (such as SELECT),
 * use [[queryAll()]], [[queryOne()]], [[queryColumn()]], [[queryScalar()]], or [[query()]].
 *
 * For example,
 *
 * ```php
 * $users = $connection->createCommand('SELECT * FROM user')->queryAll();
 * ```
 *
 * Command supports SQL statement preparation and parameter binding.
 * Call [[bindValue()]] to bind a value to a SQL parameter;
 * Call [[bindParam()]] to bind a PHP variable to a SQL parameter.
 * When binding a parameter, the SQL statement is automatically prepared.
 * You may also call [[prepare()]] explicitly to prepare a SQL statement.
 *
 * Command also supports building SQL statements by providing methods such as [[insert()]],
 * [[update()]], etc. For example, the following code will create and execute an INSERT SQL statement:
 *
 * ```php
 * $connection->createCommand()->insert('user', [
 *     'name' => 'Sam',
 *     'age' => 30,
 * ])->execute();
 * ```
 *
 * To build SELECT SQL statements, please use [[Query]] instead.
 *
 * For more details and usage information on Command, see the [guide article on Database Access Objects](guide:db-dao).
 *
 * @property string $rawSql The raw SQL with parameter values inserted into the corresponding placeholders in
 * [[sql]].
 * @property string $sql The SQL statement to be executed.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Command extends Component
{
    /**
     * @var Connection the DB connection that this command is associated with
     */
    public Connection $db;

    /**
     * @var \PDOStatement|null the PDOStatement object that this command is associated with
     */
    public ?\PDOStatement $pdoStatement = null;

    /**
     * @var int the default fetch mode for this command.
     * @see https://secure.php.net/manual/en/pdostatement.setfetchmode.php
     */
    public int $fetchMode = \PDO::FETCH_ASSOC;

    /**
     * @var array the parameters (name => value) that are bound to the current PDO statement.
     * This property is maintained by methods such as [[bindValue()]]. It is mainly provided for logging purpose
     * and is used to generate [[rawSql]]. Do not modify it directly.
     */
    public array $params = [];

    /**
     * @var int|null the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire. And use a negative number to indicate
     * query cache should not be used.
     * @see cache()
     */
    public ?int $queryCacheDuration = null;

    /**
     * @var \Yew\Framework\Caching\Dependency|null the dependency to be associated with the cached query result for this command
     * @see cache()
     */
    public ?Dependency $queryCacheDependency = null;

    /**
     * @var array pending parameters to be bound to the current PDO statement.
     */
    protected array $_pendingParams = [];

    /**
     * @var string|null the SQL statement that this command represents
     */
    protected ?string $_sql = null;

    /**
     * @var string|null name of the table, which schema, should be refreshed after command execution.
     */
    protected ?string $_refreshTableName = null;

    /**
     * @var string|false|null the isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     */
    protected $_isolationLevel = false;

    /**
     * @var callable a callable (e.g. anonymous function) that is called when [[\Yew\Framework\Db\Exception]] is thrown
     * when executing the command.
     */
    protected $_retryHandler;

    /**
     * Enables query cache for this command.
     * @param int|null $duration the number of seconds that query result of this command can remain valid in the cache.
     * If this is not set, the value of [[Connection::queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @param \Yew\Framework\Caching\Dependency $dependency the cache dependency associated with the cached query result.
     * @return $this the command object itCommand
     */
    public function cache(?int $duration = null, ?Dependency $dependency = null): self
    {
        $this->queryCacheDuration = $duration === null ? $this->db->queryCacheDuration : $duration;
        $this->queryCacheDependency = $dependency;
        return $this;
    }

    /**
     * Disables query cache for this command.
     * @return $this the command object itCommand
     */
    public function noCache(): self
    {
        $this->queryCacheDuration = -1;
        return $this;
    }

    /**
     * Returns the SQL statement for this command.
     * @return string the SQL statement to be executed
     */
    public function getSql(): string
    {
        return $this->_sql;
    }

    /**
     * Specifies the SQL statement to be executed. The SQL statement will be quoted using [[Connection::quoteSql()]].
     * The previous SQL (if any) will be discarded, and [[params]] will be cleared as well. See [[reset()]]
     * for details.
     *
     * @param string|null $sql the SQL statement to be set.
     * @return $this this command instance
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @see reset()
     * @see cancel()
     */
    public function setSql(?string $sql): self
    {
        if ($sql !== $this->_sql) {
            $this->cancel();
            $this->reset();
            $this->_sql = $this->db->quoteSql($sql);
        }

        return $this;
    }

    /**
     * Specifies the SQL statement to be executed. The SQL statement will not be modified in any way.
     * The previous SQL (if any) will be discarded, and [[params]] will be cleared as well. See [[reset()]]
     * for details.
     *
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     * @since 2.0.13
     * @see reset()
     * @see cancel()
     */
    public function setRawSql(string $sql): self
    {
        if ($sql !== $this->_sql) {
            $this->cancel();
            $this->reset();
            $this->_sql = $sql;
        }

        return $this;
    }

    /**
     * Returns the raw SQL by inserting parameter values into the corresponding placeholders in [[sql]].
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid SQL due to improper replacement of parameter placeholders.
     * @return string the raw SQL with parameter values inserted into the corresponding placeholders in [[sql]].
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function getRawSql(): string
    {
        if (empty($this->params)) {
            return $this->_sql;
        }
        $params = [];
        foreach ($this->params as $name => $value) {
            if (is_string($name) && strncmp(':', $name, 1)) {
                $name = ':' . $name;
            }
            if (is_string($value)) {
                $params[$name] = $this->db->quoteValue($value);
            } elseif (is_bool($value)) {
                $params[$name] = ($value ? 'TRUE' : 'FALSE');
            } elseif ($value === null) {
                $params[$name] = 'NULL';
            } elseif ((!is_object($value) && !is_resource($value)) || $value instanceof Expression) {
                $params[$name] = $value;
            }
        }
        if (!isset($params[1])) {
            return strtr($this->_sql, $params);
        }
        $sql = '';
        foreach (explode('?', $this->_sql) as $i => $part) {
            $sql .= ($params[$i] ?? '') . $part;
        }

        return $sql;
    }

    /**
     * Prepares the SQL statement to be executed.
     * For complex SQL statement that is to be executed multiple times,
     * this may improve performance.
     * For SQL statement with binding parameters, this method is invoked
     * automatically.
     * @param bool|null $forRead whether this method is called for a read query. If null, it means
     * the SQL statement should be used to determine whether it is for read or write.
     * @throws Exception if there is any DB error
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function prepare(?bool $forRead = null)
    {
        if ($this->pdoStatement) {
            $this->bindPendingParams();
            return;
        }

        $sql = $this->getSql();

        if ($this->db->getTransaction()) {
            // master is in a transaction. use the same connection.
            $forRead = false;
        }
        if ($forRead || $forRead === null && $this->db->getSchema()->isReadQuery($sql)) {
            $pdo = $this->db->getSlavePdo();
        } else {
            $pdo = $this->db->getMasterPdo();
        }

        try {
            $this->pdoStatement = $pdo->prepare($sql);
            $this->bindPendingParams();
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nFailed to prepare SQL: $sql";
            $errorInfo = $e instanceof \PDOException ? $e->errorInfo : null;
            throw new Exception($message, $errorInfo, (int)$e->getCode(), $e);
        }
    }

    /**
     * Cancels the execution of the SQL statement.
     * This method mainly sets [[pdoStatement]] to be null.
     */
    public function cancel()
    {
        $this->pdoStatement = null;
    }

    /**
     * Binds a parameter to the SQL statement to be executed.
     * @param string|int $name parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value the PHP variable to bind to the SQL statement parameter (passed by reference)
     * @param int|null $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @param int|null $length length of the data type
     * @param mixed $driverOptions the driver-specific options
     * @return $this the current command being executed
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @see https://secure.php.net/manual/en/function.PDOStatement-bindParam.php
     */
    public function bindParam($name, &$value, ?int $dataType = null, ?int $length = null, $driverOptions = null): self
    {
        $this->prepare();

        if ($dataType === null) {
            $dataType = $this->db->getSchema()->getPdoType($value);
        }
        if ($length === null) {
            $this->pdoStatement->bindParam($name, $value, $dataType);
        } elseif ($driverOptions === null) {
            $this->pdoStatement->bindParam($name, $value, $dataType, $length);
        } else {
            $this->pdoStatement->bindParam($name, $value, $dataType, $length, $driverOptions);
        }
        $this->params[$name] = &$value;

        return $this;
    }

    /**
     * Binds pending parameters that were registered via [[bindValue()]] and [[bindValues()]].
     * Note that this method requires an active [[pdoStatement]].
     */
    protected function bindPendingParams()
    {
        foreach ($this->_pendingParams as $name => $value) {
            $this->pdoStatement->bindValue($name, $value[0], $value[1]);
        }
    }

    /**
     * Binds a value to a parameter.
     * @param string|int $name Parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @param int|null $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @return $this the current command being executed
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @see https://secure.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, ?int $dataType = null): Command
    {
        if ($dataType === null) {
            $dataType = $this->db->getSchema()->getPdoType($value);
        }
        $this->_pendingParams[$name] = [$value, $dataType];
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array|null $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values,
     * e.g. `[':name' => 'John', ':age' => 25]`. By default, the PDO type of each value is determined
     * by its PHP type. You may explicitly specify the PDO type by using a [[Yew\Framework\Db\PdoValue]] class: `new PdoValue(value, type)`,
     * e.g. `[':name' => 'John', ':profile' => new PdoValue($profile, \PDO::PARAM_LOB)]`.
     * @return $this the current command being executed
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function bindValues(?array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $schema = $this->db->getSchema();
        foreach ($values as $name => $value) {
            if (is_array($value)) { // TODO: Drop in Yii 2.1
                $this->_pendingParams[$name] = $value;
                $this->params[$name] = $value[0];
            } elseif ($value instanceof PdoValue) {
                $this->_pendingParams[$name] = [$value->getValue(), $value->getType()];
                $this->params[$name] = $value->getValue();
            } else {
                $type = $schema->getPdoType($value);
                $this->_pendingParams[$name] = [$value, $type];
                $this->params[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing a SQL query that returns result set, such as `SELECT`.
     * @return DataReader the reader object for fetching the query result
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function query()
    {
        return $this->queryInternal('');
    }

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param int|null $fetchMode the result fetch mode. Please refer to [PHP manual](https://secure.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return array all rows of the query result. Each array element is an array representing a row of data.
     * An empty array is returned if the query results in nothing.
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function queryAll(?int $fetchMode = null)
    {
        return $this->queryInternal('fetchAll', $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This method is best used when only the first row of result is needed for a query.
     * @param int|null $fetchMode the result fetch mode. Please refer to [PHP manual](https://secure.php.net/manual/en/pdostatement.setfetchmode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return array|false the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function queryOne(?int $fetchMode = null)
    {
        return $this->queryInternal('fetch', $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This method is best used when only a single value is needed for a query.
     * @return string|null|false the value of the first column in the first row of the query result.
     * False is returned if there is no value.
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function queryScalar()
    {
        $result = $this->queryInternal('fetchColumn', 0);
        if (is_resource($result) && get_resource_type($result) === 'stream') {
            return stream_get_contents($result);
        }

        return $result;
    }

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This method is best used when only the first column of result (i.e. the first element in each row)
     * is needed for a query.
     * @return array the first column of the query result. Empty array is returned if the query results in nothing.
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function queryColumn()
    {
        return $this->queryInternal('fetchAll', \PDO::FETCH_COLUMN);
    }

    /**
     * Creates an INSERT command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->insert('user', [
     *     'name' => 'Sam',
     *     'age' => 30,
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array|Query $columns the column data (name => value) to be inserted into the table or instance
     * of [[Yew\Framework\Db\Query|Query]] to perform INSERT INTO ... SELECT SQL statement.
     * Passing of [[Yew\Framework\Db\Query|Query]] is available since version 2.0.11.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function insert(string $table, $columns): self
    {
        $params = [];
        $sql = $this->db->getQueryBuilder()->insert($table, $columns, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a batch INSERT command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and quote the values to be inserted.
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * Also note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array|\Generator $rows the rows to be batch inserted into the table
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function batchInsert(string $table, array $columns, $rows): self
    {
        $table = $this->db->quoteSql($table);
        $columns = array_map(function ($column) {
            return $this->db->quoteSql($column);
        }, $columns);

        $params = [];
        $sql = $this->db->getQueryBuilder()->batchInsert($table, $columns, $rows, $params);

        $this->setRawSql($sql);
        $this->bindValues($params);

        return $this;
    }

    /**
     * Creates a command to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * For example,
     *
     * ```php
     * $sql = $queryBuilder->upsert('pages', [
     *     'name' => 'Front page',
     *     'url' => 'http://example.com/', // url is unique
     *     'visits' => 0,
     * ], [
     *     'visits' => new \Yew\Framework\Db\Expression('visits + 1'),
     * ], $params);
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of [[Query]] to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array|null $params the parameters to be bound to the command.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.14
     */
    public function upsert(string $table, $insertColumns, $updateColumns = true, ?array $params = []): self
    {
        $sql = $this->db->getQueryBuilder()->upsert($table, $insertColumns, $updateColumns, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates an UPDATE command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->update('user', ['status' => 1], 'age > 30')->execute();
     * ```
     *
     * or with using parameter binding for the condition:
     *
     * ```php
     * $minAge = 30;
     * $connection->createCommand()->update('user', ['status' => 1], 'age > :minAge', [':minAge' => $minAge])->execute();
     * ```
     *
     * The method will properly escape the column names and bind the values to be updated.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array|null $params the parameters to be bound to the command
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function update(string $table, array $columns, $condition = '', ?array $params = []): Command
    {
        $sql = $this->db->getQueryBuilder()->update($table, $columns, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a DELETE command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->delete('user', 'status = 0')->execute();
     * ```
     *
     * or with using parameter binding for the condition:
     *
     * ```php
     * $status = 0;
     * $connection->createCommand()->delete('user', 'status = :status', [':status' => $status])->execute();
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table where the data will be deleted from.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array|null $params the parameters to be bound to the command
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function delete(string $table, $condition = '', ?array $params = []): Command
    {
        $sql = $this->db->getQueryBuilder()->delete($table, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a SQL command for creating a new DB table.
     *
     * The columns in the new table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     * The method [[QueryBuilder::getColumnType()]] will be called
     * to convert the abstract column types to physical ones. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string|null $options additional SQL fragment that will be appended to the generated SQL.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function createTable(string $table, array $columns, ?string $options = null): Command
    {
        $sql = $this->db->getQueryBuilder()->createTable($table, $columns, $options);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function renameTable(string $table, string $newName): self
    {
        $sql = $this->db->getQueryBuilder()->renameTable($table, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function dropTable(string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropTable($table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function truncateTable(string $table): self
    {
        $sql = $this->db->getQueryBuilder()->truncateTable($table);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. [[\Yew\Framework\Db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function addColumn(string $table, string $column, string $type): self
    {
        $sql = $this->db->getQueryBuilder()->addColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function dropColumn(string $table, string $column): self
    {
        $sql = $this->db->getQueryBuilder()->dropColumn($table, $column);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function renameColumn(string $table, string $oldName, string $newName): self
    {
        $sql = $this->db->getQueryBuilder()->renameColumn($table, $oldName, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the column type. [[\Yew\Framework\Db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function alterColumn(string $table, string $column, string $type): self
    {
        $sql = $this->db->getQueryBuilder()->alterColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a primary key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function addPrimaryKey(string $name, string $table, $columns): self
    {
        $sql = $this->db->getQueryBuilder()->addPrimaryKey($name, $table, $columns);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function dropPrimaryKey(string $name, string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropPrimaryKey($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas.
     * @param string|null $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string|null $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function addForeignKey(string $name, string $table, $columns, string $refTable, $refColumns, ?string $delete = null, ?string $update = null): self
    {
        $sql = $this->db->getQueryBuilder()->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function dropForeignKey(string $name, string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropForeignKey($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     * by commas. The column names will be properly quoted by the method.
     * @param bool|null $unique whether to add UNIQUE constraint on the created index.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function createIndex(string $name, string $table, $columns, ?bool $unique = false): Command
    {
        $sql = $this->db->getQueryBuilder()->createIndex($name, $table, $columns, $unique);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function dropIndex(string $name, string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropIndex($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding an unique constraint to an existing table.
     * @param string $name the name of the unique constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the unique constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas.
     * The name will be properly quoted by the method.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function addUnique(string $name, string $table, $columns): self
    {
        $sql = $this->db->getQueryBuilder()->addUnique($name, $table, $columns);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping an unique constraint.
     * @param string $name the name of the unique constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose unique constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function dropUnique(string $name, string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropUnique($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     * @param string $name the name of the check constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function addCheck(string $name, string $table, string $expression): self
    {
        $sql = $this->db->getQueryBuilder()->addCheck($name, $table, $expression);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a check constraint.
     * @param string $name the name of the check constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose check constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return Command
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function dropCheck(string $name, string $table): Command
    {
        $sql = $this->db->getQueryBuilder()->dropCheck($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a default value constraint to an existing table.
     * @param string $name the name of the default value constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the default value constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $column the name of the column to that the constraint will be added on.
     * The name will be properly quoted by the method.
     * @param mixed $value default value.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function addDefaultValue(string $name, string $table, string $column, $value): self
    {
        $sql = $this->db->getQueryBuilder()->addDefaultValue($name, $table, $column, $value);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a default value constraint.
     * @param string $name the name of the default value constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose default value constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.13
     */
    public function dropDefaultValue(string $name, string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropDefaultValue($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or the maximum existing value +1.
     * @param string $table the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have the maximum existing value +1.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     * @throws \Throwable
     */
    public function resetSequence(string $table, $value = null): self
    {
        $sql = $this->db->getQueryBuilder()->resetSequence($table, $value);

        return $this->setSql($sql);
    }

    /**
     * Executes a db command resetting the sequence value of a table's primary key.
     * Reason for execute is that some databases (Oracle) need several queries to do so.
     * The sequence is reset such that the primary key of the next new row inserted
     * will have the specified value or the maximum existing value +1.
     * @param string $table the name of the table whose primary key sequence is reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have the maximum existing value +1.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     * @throws \Throwable
     * @since 2.0.16
     */
    public function executeResetSequence(string $table, $value = null)
    {
        $this->db->getQueryBuilder()->executeResetSequence($table, $value);
    }

    /**
     * Builds a SQL command for enabling or disabling integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema name of the tables. Defaults to empty string, meaning the current
     * or default schema.
     * @param string $table the table name.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     * @throws \Throwable
     */
    public function checkIntegrity(bool $check = true, ?string $schema = '', ?string $table = ''): self
    {
        $sql = $this->db->getQueryBuilder()->checkIntegrity($check, $schema, $table);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.8
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): self
    {
        $sql = $this->db->getQueryBuilder()->addCommentOnColumn($table, $column, $comment);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.8
     */
    public function addCommentOnTable(string $table, string $comment): self
    {
        $sql = $this->db->getQueryBuilder()->addCommentOnTable($table, $comment);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for dropping comment from column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.8
     */
    public function dropCommentFromColumn(string $table, string $column): self
    {
        $sql = $this->db->getQueryBuilder()->dropCommentFromColumn($table, $column);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Builds a SQL command for dropping comment from table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @return $this the command object itCommand
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.8
     */
    public function dropCommentFromTable(string $table): self
    {
        $sql = $this->db->getQueryBuilder()->dropCommentFromTable($table);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL View.
     *
     * @param string $viewName the name of the view to be created.
     * @param string|Query $subquery the select statement which defines the view.
     * This can be either a string or a [[Query]] object.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.14
     */
    public function createView(string $viewName, $subquery): self
    {
        $sql = $this->db->getQueryBuilder()->createView($viewName, $subquery);

        return $this->setSql($sql)->requireTableSchemaRefresh($viewName);
    }

    /**
     * Drops a SQL View.
     *
     * @param string $viewName the name of the view to be dropped.
     * @return $this the command object itCommand.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.14
     */
    public function dropView(string $viewName): self
    {
        $sql = $this->db->getQueryBuilder()->dropView($viewName);

        return $this->setSql($sql)->requireTableSchemaRefresh($viewName);
    }

    /**
     * Executes the SQL statement.
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     * @return int number of rows affected by the execution.
     * @throws Exception execution failed
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    public function execute(): ?int
    {
        $sql = $this->getSql();
        list($profile, $rawSql) = $this->logQuery(__METHOD__);

        if ($sql == '') {
            return 0;
        }

        $this->prepare(false);

        try {
            $profile and Yew::beginProfile($rawSql, __METHOD__);

            $this->internalExecute($rawSql);
            $n = $this->pdoStatement->rowCount();

            $profile and Yew::endProfile($rawSql, __METHOD__);

            $this->refreshTableSchema();

            return $n;
        } catch (Exception $e) {
            $_message = sprintf("\nFile: %s, \nLine: %s, \nCode: %s, \nMessage: %s, \nTrace: %s\n",
                $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage(), $e->getTraceAsString());
            Yew::error($_message);

            if (!$this->isBreak($e)) {
                $profile and Yew::endProfile($rawSql, __METHOD__);
                throw $this->db->getSchema()->convertException($e, $rawSql ?: $this->getRawSql());
            }
            try {
                if ($this->reconnectTimes < $this->reconnectMaxTimes) {
                    ++$this->reconnectTimes;

                    Server::$instance->getLog()->debug(sprintf("Reconnect times ...%d", $this->reconnectTimes));
                    Yew::debug(sprintf("Reconnect times ...%d", $this->reconnectTimes));

                    $this->db->close();
                    $this->db->open();

                    //PDO handle changed with new PDO connection, so pdoStatement need to reacquire.
                    //'prepare' and 'bindPendingParams' function makes no sense but just to generate new pdoStatement
                    //'rawSql' is same as before.
                    $this->pdoStatement = $this->db->pdo->prepare($this->getSql());
                    $this->bindPendingParams();
                    list($profile, $rawSql) = $this->logQuery(__METHOD__);

                    $this->internalExecute($rawSql);
                    $n = $this->pdoStatement->rowCount();

                    $contextKey = sprintf("Pdo:%s", $this->db->poolName);
                    setContextValue($contextKey, $this->db);

                    $profile and Yew::endProfile($rawSql, __METHOD__);

                    $this->refreshTableSchema();
                    return $n;
                }
            } catch (Exception $e) {
                $_message = sprintf("\nFile: %s, \nLine: %s, \nCode: %s, \nMessage: %s, \nTrace: %s\n",
                    $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage(), $e->getTraceAsString());

                Yew::error($_message);
                $profile and Yew::endProfile($rawSql, __METHOD__);
                throw $this->db->getSchema()->convertException($e, $rawSql ?: $this->getRawSql());
            }
        }
        return null;
    }

    /**
     * Logs the current database query if query logging is enabled and returns
     * the profiling token if profiling is enabled.
     * @param string $category the log category.
     * @return array array of two elements, the first is boolean of whether profiling is enabled or not.
     * The second is the rawSql if it has been created.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     */
    protected function logQuery(string $category): array
    {
        if ($this->db->enableLogging) {
            $rawSql = $this->getRawSql();
            Yew::info($rawSql, $category);
        }
        if (!$this->db->enableProfiling) {
            return [false, $rawSql ?? null];
        }

        return [true, $rawSql ?? $this->getRawSql()];
    }

    /**
     * Performs the actual DB query of a SQL statement.
     * @param string $method method of PDOStatement to be called
     * @param int|null $fetchMode the result fetch mode. Please refer to [PHP manual](https://secure.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return mixed the method execution result
     * @throws Exception if the query causes any problem
     * @throws NotSupportedException
     * @throws \Throwable
     * @throws InvalidConfigException
     * @since 2.0.1 this method is protected (was private before).
     */
    protected function queryInternal(string $method, ?int $fetchMode = null)
    {
        list($profile, $rawSql) = $this->logQuery('Yew\Framework\Db\Command::query');

        $result = [];
        if ($method !== '') {
            $info = $this->db->getQueryCacheInfo($this->queryCacheDuration, $this->queryCacheDependency);
            if (is_array($info)) {
                /* @var $cache CacheInterface */
                $cache = $info[0];
                $rawSql = $rawSql ?: $this->getRawSql();
                $cacheKey = $this->getCacheKey($method, $fetchMode, $rawSql);
                $result = $cache->get($cacheKey);
                if (is_array($result) && isset($result[0])) {
                    Yew::debug('Query result served from cache', 'Yew\Framework\Db\Command::query');
                    return $result[0];
                }
            }
        }

        $this->prepare(true);

        try {
            $profile and Yew::beginProfile($rawSql, 'Yew\Framework\Db\Command::query');

            $this->internalExecute($rawSql);

            if ($method === '') {
                $result = new DataReader($this);
            } else {
                if ($fetchMode === null) {
                    $fetchMode = $this->fetchMode;
                }
                $result = call_user_func_array([$this->pdoStatement, $method], (array)$fetchMode);
                $this->pdoStatement->closeCursor();
            }

            $profile and Yew::endProfile($rawSql, 'Yew\Framework\Db\Command::query');
        } catch (Exception $e) {
            $_message = sprintf("\nFile: %s, \nLine: %s, \nCode: %s, \nMessage: %s, \nTrace: %s\n",
                $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage(), $e->getTraceAsString());

            Yew::error($_message);

            if (!$this->isBreak($e)) {
                $profile and Yew::endProfile($rawSql, __METHOD__);
                throw $this->db->getSchema()->convertException($e, $rawSql ?: $this->getRawSql());
            }
            try {
                if ($this->reconnectTimes < $this->reconnectMaxTimes) {
                    ++$this->reconnectTimes;

                    Server::$instance->getLog()->debug(sprintf("Reconnect times ...%d", $this->reconnectTimes));
                    Yew::debug(sprintf("Reconnect times ...%d", $this->reconnectTimes));

                    $this->db->close();
                    $this->db->open();

                    //PDO handle changed with new PDO connection, so pdoStatement need to reacquire.
                    //'prepare' and 'bindPendingParams' function makes no sense but just to generate new pdoStatement
                    //'rawSql' is same as before.
                    $this->pdoStatement = $this->db->pdo->prepare($this->getSql());
                    $this->bindPendingParams();
                    list($profile, $rawSql) = $this->logQuery('Yew\Framework\Db\Command::query');

                    $this->internalExecute($rawSql);

                    if ($method === '') {
                        $result = new DataReader($this);
                    } else {
                        if ($fetchMode === null) {
                            $fetchMode = $this->fetchMode;
                        }
                        $result = call_user_func_array([$this->pdoStatement, $method], (array)$fetchMode);
                        $this->pdoStatement->closeCursor();
                    }

                    $contextKey = sprintf("Pdo:%s", $this->db->poolName);
                    setContextValue($contextKey, $this->db);

                    $profile and Yew::endProfile($rawSql, 'Yew\Framework\Db\Command::query');
                }
            } catch (Exception $e) {
                $_message = sprintf("\nFile: %s, \nLine: %s, \nCode: %s, \nMessage: %s, \nTrace: %s\n",
                    $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage(), $e->getTraceAsString());

                Yew::error($_message);
                $profile and Yew::endProfile($rawSql, __METHOD__);
                throw $this->db->getSchema()->convertException($e, $rawSql ?: $this->getRawSql());
            }
        }

        if (isset($cache, $cacheKey, $info)) {
            $cache->set($cacheKey, [$result], $info[1], $info[2]);
            Yew::debug('Saved query result in cache', 'Yew\Framework\Db\Command::query');
        }

        return $result;
    }

    /**
     * Returns the cache key for the query.
     *
     * @param string $method method of PDOStatement to be called
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](https://secure.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes.
     * @param string $rawSql the raw SQL with parameter values inserted into the corresponding placeholders
     * @return array the cache key
     * @since 2.0.16
     */
    protected function getCacheKey(string $method, int $fetchMode, string $rawSql): array
    {
        return [
            __CLASS__,
            $method,
            $fetchMode,
            $this->db->dsn,
            $this->db->username,
            $rawSql,
        ];
    }

    /**
     * Marks a specified table schema to be refreshed after command execution.
     * @param string $name name of the table, which schema should be refreshed.
     * @return $this this command instance
     * @since 2.0.6
     */
    protected function requireTableSchemaRefresh(string $name): Command
    {
        $this->_refreshTableName = $name;
        return $this;
    }

    /**
     * Refreshes table schema, which was marked by [[requireTableSchemaRefresh()]].
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.6
     */
    protected function refreshTableSchema()
    {
        if ($this->_refreshTableName !== null) {
            $this->db->getSchema()->refreshTableSchema($this->_refreshTableName);
        }
    }

    /**
     * Marks the command to be executed in transaction.
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     * @return $this this command instance.
     * @since 2.0.14
     */
    protected function requireTransaction(?string $isolationLevel = null): Command
    {
        $this->_isolationLevel = $isolationLevel;
        return $this;
    }

    /**
     * Sets a callable (e.g. anonymous function) that is called when [[Exception]] is thrown
     * when executing the command. The signature of the callable should be:
     *
     * ```php
     * function (\Yew\Framework\Db\Exception $e, $attempt)
     * {
     *     // return true or false (whether to retry the command or rethrow $e)
     * }
     * ```
     *
     * The callable will recieve a database exception thrown and a current attempt
     * (to execute the command) number starting from 1.
     *
     * @param callable $handler a PHP callback to handle database exceptions.
     * @return $this this command instance.
     * @since 2.0.14
     */
    protected function setRetryHandler(callable $handler): Command
    {
        $this->_retryHandler = $handler;
        return $this;
    }

    /**
     * Executes a prepared statement.
     *
     * It's a wrapper around [[\PDOStatement::execute()]] to support transactions
     * and retry handlers.
     *
     * @param string|null $rawSql the rawSql if it has been created.
     * @throws Exception if execution failed.
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     * @since 2.0.14
     */
    protected function internalExecute(?string $rawSql = null)
    {
        $attempt = 0;
        while (true) {
            try {
                if (
                    ++$attempt === 1
                    && $this->_isolationLevel !== false
                    && $this->db->getTransaction() === null
                ) {
                    $this->db->transaction(function () use ($rawSql) {
                        $this->internalExecute($rawSql);
                    }, $this->_isolationLevel);
                } else {
                    $this->pdoStatement->execute();
                }

                $this->reconnectTimes = 0;
                break;
            } catch (\Exception $e) {
                $rawSql = $rawSql ?: $this->getRawSql();
                $e = $this->db->getSchema()->convertException($e, $rawSql);

                if ($this->_retryHandler === null || !call_user_func($this->_retryHandler, $e, $attempt)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Resets command properties to their initial state.
     *
     * @since 2.0.13
     */
    protected function reset()
    {
        $this->_sql = null;
        $this->_pendingParams = [];
        $this->params = [];
        $this->_refreshTableName = null;
        $this->_isolationLevel = false;
        $this->_retryHandler = null;
    }

    /**
     * @var int temporary reconnect times
     */
    protected int $reconnectTimes = 0;

    /** @var int reconnect max times */
    protected int $reconnectMaxTimes = 5;

    /**
     * @var array
     */
    protected array $breakMatchMessages = [
        "server has gone away",
        "no connection to the server",
        "Lost connection",
        "is dead or not enabled",
        "Error while sending",
        "decryption failed or bad record mac",
        "server closed the connection unexpectedly",
        "SSL connection has been closed unexpectedly",
        "Error writing data to the connection",
        "Resource deadlock avoided",
        "failed with errno",
        "child connection forced to terminate due to client_idle_limit",
        "query_wait_timeout",
        "reset by peer",
        "Physical connection is not usable",
        "TCP Provider: Error code 0x68",
        "ORA-03114",
        "Packets out of order. Expected",
        "Adaptive Server connection failed",
        "Communication link failure",
        "connection is no longer usable",
        "Login timeout expired",
        "running with the --read-only option so it cannot execute this statement",
        "The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.",
        "SSL: Connection timed out",
        "SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected",
        "SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.",
        "SQLSTATE[HY000] [2002] Connection refused",
        "SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again",
        "SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known",
        "SQLSTATE[HY000] [2002] Connection timed out",
        "SQLSTATE[42000]: Syntax error or access violation: 1064",
        "SQLSTATE[HY093]: Invalid parameter number",
        "Error while sending QUERY packet",
        "Error reading result set's header",
        "Packets out of order. Expected 1 received 0",
    ];

    /**
     * @param \Exception $exception
     * @return bool
     */
    protected function isBreak(\Exception $exception): bool
    {
        $message = $exception->getMessage();

        foreach ($this->breakMatchMessages as $match) {
            if (false !== stripos($message, $match)) {
                return true;
            }
        }

        return false;
    }


    public function __destruct()
    {
        $this->reset();
    }
}
