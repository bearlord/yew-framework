<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Cubrid;

use Yew\Framework\Exception\InvalidArgumentException;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\NotSupportedException;
use Yew\Framework\Db\Constraint;
use Yew\Framework\Db\Exception;
use Yew\Framework\Db\Expression;

/**
 * QueryBuilder is the query builder for CUBRID databases (version 9.3.x and higher).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class QueryBuilder extends \Yew\Framework\Db\QueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public array $typeMap = [
        Schema::TYPE_PK => 'int NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_UPK => 'int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_BIGPK => 'bigint NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_UBIGPK => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_CHAR => 'char(1)',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'varchar',
        Schema::TYPE_TINYINT => 'smallint',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'int',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float(7)',
        Schema::TYPE_DOUBLE => 'double(15)',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'datetime',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'blob',
        Schema::TYPE_BOOLEAN => 'smallint',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];


    /**
     * {@inheritdoc}
     */
    protected function defaultExpressionBuilders(): array
    {
        return array_merge(parent::defaultExpressionBuilders(), [
            'Yew\Framework\Db\Conditions\LikeCondition' => 'Yew\Framework\Db\Cubrid\Conditions\LikeConditionBuilder',
        ]);
    }

    /**
     * {@inheritdoc}
     * @see https://www.cubrid.org/manual/en/9.3.0/sql/query/merge.html
     * @throws Exception|InvalidConfigException
     */
    public function upsert(string $table, $insertColumns, $updateColumns, array &$params): string
    {
        /** @var Constraint[] $constraints */
        list($uniqueNames, $insertNames, $updateNames) = $this->prepareUpsertColumns($table, $insertColumns, $updateColumns, $constraints);
        if (empty($uniqueNames)) {
            return $this->insert($table, $insertColumns, $params);
        }

        $onCondition = ['or'];
        $quotedTableName = $this->db->quoteTableName($table);
        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            foreach ($constraint->columnNames as $name) {
                $quotedName = $this->db->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=\"EXCLUDED\".$quotedName";
            }
            $onCondition[] = $constraintCondition;
        }
        $on = $this->buildCondition($onCondition, $params);
        list(, $placeholders, $values, $params) = $this->prepareInsertValues($table, $insertColumns, $params);
        $mergeSql = 'MERGE INTO ' . $this->db->quoteTableName($table) . ' '
            . 'USING (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' ')) . ') AS "EXCLUDED" (' . implode(', ', $insertNames) . ') '
            . "ON ($on)";
        $insertValues = [];
        foreach ($insertNames as $name) {
            $quotedName = $this->db->quoteColumnName($name);
            if (strrpos($quotedName, '.') === false) {
                $quotedName = '"EXCLUDED".' . $quotedName;
            }
            $insertValues[] = $quotedName;
        }
        $insertSql = 'INSERT (' . implode(', ', $insertNames) . ')'
            . ' VALUES (' . implode(', ', $insertValues) . ')';
        if ($updateColumns === false) {
            return "$mergeSql WHEN NOT MATCHED THEN $insertSql";
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            foreach ($updateNames as $name) {
                $quotedName = $this->db->quoteColumnName($name);
                if (strrpos($quotedName, '.') === false) {
                    $quotedName = '"EXCLUDED".' . $quotedName;
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }
        list($updates, $params) = $this->prepareUpdateSets($table, $updateColumns, $params);
        $updateSql = 'UPDATE SET ' . implode(', ', $updates);
        return "$mergeSql WHEN MATCHED THEN $updateSql WHEN NOT MATCHED THEN $insertSql";
    }

    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     * @param string $tableName the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have a value 1.
     * @return string the SQL statement for resetting sequence
     * @throws Exception
     * @throws NotSupportedException
     * @throws InvalidConfigException
     */
    public function resetSequence(string $tableName, $value = null): string
    {
        $table = $this->db->getTableSchema($tableName);
        if ($table !== null && $table->sequenceName !== null) {
            $tableName = $this->db->quoteTableName($tableName);
            if ($value === null) {
                $key = reset($table->primaryKey);
                $value = (int) $this->db->createCommand("SELECT MAX(`$key`) FROM " . $this->db->schema->quoteTableName($tableName))->queryScalar() + 1;
            } else {
                $value = (int) $value;
            }

            return 'ALTER TABLE ' . $this->db->schema->quoteTableName($tableName) . " AUTO_INCREMENT=$value;";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: $tableName");
        }

        throw new InvalidArgumentException("There is not sequence associated with table '$tableName'.");
    }

    /**
     * {@inheritdoc}
     */
    public function buildLimit(int $limit, int $offset): string
    {
        $sql = '';
        // limit is not optional in CUBRID
        // http://www.cubrid.org/manual/90/en/LIMIT%20Clause
        // "You can specify a very big integer for row_count to display to the last row, starting from a specific row."
        if ($this->hasLimit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->hasOffset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->hasOffset($offset)) {
            $sql = "LIMIT 9223372036854775807 OFFSET $offset"; // 2^63-1
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function selectExists(string $rawSql): string
    {
        return 'SELECT CASE WHEN EXISTS(' . $rawSql . ') THEN 1 ELSE 0 END';
    }

    /**
     * {@inheritdoc}
     * @see http://www.cubrid.org/manual/93/en/sql/schema/table.html#drop-index-clause
     * @throws NotSupportedException
     */
    public function dropIndex(string $name, string $table): string
    {
        /** @var Schema $schema */
        $schema = $this->db->getSchema();
        foreach ($schema->getTableUniques($table) as $unique) {
            if ($unique->name === $name) {
                return $this->dropUnique($name, $table);
            }
        }

        return 'DROP INDEX ' . $this->db->quoteTableName($name) . ' ON ' . $this->db->quoteTableName($table);
    }

    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by CUBRID.
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by CUBRID.');
    }

    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by CUBRID.
     */
    public function dropCheck(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by CUBRID.');
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     * @since 2.0.8
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        $definition = $this->getColumnDefinition($table, $column);
        $definition = trim(preg_replace("/COMMENT '(.*?)'/i", '', $definition));

        return 'ALTER TABLE ' . $this->db->quoteTableName($table)
        . ' CHANGE ' . $this->db->quoteColumnName($column)
        . ' ' . $this->db->quoteColumnName($column)
        . (empty($definition) ? '' : ' ' . $definition)
        . ' COMMENT ' . $this->db->quoteValue($comment);
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' COMMENT ' . $this->db->quoteValue($comment);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     * @since 2.0.8
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        return $this->addCommentOnColumn($table, $column, '');
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function dropCommentFromTable(string $table): string
    {
        return $this->addCommentOnTable($table, '');
    }


    /**
     * Gets column definition.
     *
     * @param string $table table name
     * @param string $column column name
     * @return null|string the column definition
     * @throws Exception in case when table does not contain column
     * @since 2.0.8
     */
    private function getColumnDefinition($table, $column): ?string
    {
        $row = $this->db->createCommand('SHOW CREATE TABLE ' . $this->db->quoteTableName($table))->queryOne();
        if ($row === false) {
            throw new Exception("Unable to find column '$column' in table '$table'.");
        }
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        $sql = preg_replace('/^[^(]+\((.*)\).*$/', '\1', $sql);
        $sql = str_replace(', [', ",\n[", $sql);
        if (preg_match_all('/^\s*\[(.*?)\]\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $column) {
                    return $matches[2][$i];
                }
            }
        }

        return null;
    }
}
