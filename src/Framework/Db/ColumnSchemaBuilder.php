<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

use Yew\Framework\Base\BaseObject;
use Yew\Framework\Helpers\StringHelper;

/**
 * ColumnSchemaBuilder helps to define database schema types using a PHP interface.
 *
 * See [[SchemaBuilderTrait]] for more detailed description and usage examples.
 *
 * @author Vasenin Matvey <vaseninm@gmail.com>
 * @since 2.0.6
 */
class ColumnSchemaBuilder extends BaseObject
{
    // Internally used constants representing categories that abstract column types fall under.
    // See [[$categoryMap]] for mappings of abstract column types to category.
    // @since 2.0.8
    const CATEGORY_PK = 'pk';
    const CATEGORY_STRING = 'string';
    const CATEGORY_NUMERIC = 'numeric';
    const CATEGORY_TIME = 'time';
    const CATEGORY_OTHER = 'other';

    /**
     * @var string the column type definition such as INTEGER, VARCHAR, DATETIME, etc.
     */
    protected string $type;

    /**
     * @var int|string|array column size or precision definition. This is what goes into the parenthesis after
     * the column type. This can be either a string, an integer or an array. If it is an array, the array values will
     * be joined into a string separated by comma.
     */
    protected $length;

    /**
     * @var bool|null whether the column is or not nullable. If this is `true`, a `NOT NULL` constraint will be added.
     * If this is `false`, a `NULL` constraint will be added.
     */
    protected ?bool $isNotNull = null;

    /**
     * @var bool whether the column values should be unique. If this is `true`, a `UNIQUE` constraint will be added.
     */
    protected bool $isUnique = false;

    /**
     * @var string|null the `CHECK` constraint for the column.
     */
    protected ?string $check = null;

    /**
     * @var mixed default value of the column.
     */
    protected $default;

    /**
     * @var mixed SQL string to be appended to column schema definition.
     * @since 2.0.9
     */
    protected $append;

    /**
     * @var bool whether the column values should be unsigned. If this is `true`, an `UNSIGNED` keyword will be added.
     * @since 2.0.7
     */
    protected bool $isUnsigned = false;

    /**
     * @var string|null the column after which this column will be added.
     * @since 2.0.8
     */
    protected ?string $after = null;

    /**
     * @var bool whether this column is to be inserted at the beginning of the table.
     * @since 2.0.8
     */
    protected ?bool $isFirst = null;

    /**
     * @var array mapping of abstract column types (keys) to type categories (values).
     * @since 2.0.8
     */
    public array $categoryMap = [
        Schema::TYPE_PK => self::CATEGORY_PK,
        Schema::TYPE_UPK => self::CATEGORY_PK,
        Schema::TYPE_BIGPK => self::CATEGORY_PK,
        Schema::TYPE_UBIGPK => self::CATEGORY_PK,
        Schema::TYPE_CHAR => self::CATEGORY_STRING,
        Schema::TYPE_STRING => self::CATEGORY_STRING,
        Schema::TYPE_TEXT => self::CATEGORY_STRING,
        Schema::TYPE_TINYINT => self::CATEGORY_NUMERIC,
        Schema::TYPE_SMALLINT => self::CATEGORY_NUMERIC,
        Schema::TYPE_INTEGER => self::CATEGORY_NUMERIC,
        Schema::TYPE_BIGINT => self::CATEGORY_NUMERIC,
        Schema::TYPE_FLOAT => self::CATEGORY_NUMERIC,
        Schema::TYPE_DOUBLE => self::CATEGORY_NUMERIC,
        Schema::TYPE_DECIMAL => self::CATEGORY_NUMERIC,
        Schema::TYPE_DATETIME => self::CATEGORY_TIME,
        Schema::TYPE_TIMESTAMP => self::CATEGORY_TIME,
        Schema::TYPE_TIME => self::CATEGORY_TIME,
        Schema::TYPE_DATE => self::CATEGORY_TIME,
        Schema::TYPE_BINARY => self::CATEGORY_OTHER,
        Schema::TYPE_BOOLEAN => self::CATEGORY_NUMERIC,
        Schema::TYPE_MONEY => self::CATEGORY_NUMERIC,
    ];
    /**
     * @var Connection|null the current database connection. It is used mainly to escape strings
     * safely when building the final column schema string.
     * @since 2.0.8
     */
    public ?Connection $db = null;

    /**
     * @var string|null comment value of the column.
     * @since 2.0.8
     */
    public ?string $comment = null;

    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * @param string $type type of the column. See [[$type]].
     * @param int|string|array $length length or precision of the column. See [[$length]].
     * @param Connection|null $db the current database connection. See [[$db]].
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct(string $type, $length = null, Connection $db = null, array $config = [])
    {
        $this->type = $type;
        $this->length = $length;
        $this->db = $db;
        parent::__construct($config);
    }

    /**
     * Adds a `NOT NULL` constraint to the column.
     * @return $this
     */
    public function notNull(): ColumnSchemaBuilder
    {
        $this->isNotNull = true;
        return $this;
    }

    /**
     * Adds a `NULL` constraint to the column.
     * @return $this
     * @since 2.0.9
     */
    public function null(): ColumnSchemaBuilder
    {
        $this->isNotNull = false;
        return $this;
    }

    /**
     * Adds a `UNIQUE` constraint to the column.
     * @return $this
     */
    public function unique(): ColumnSchemaBuilder
    {
        $this->isUnique = true;
        return $this;
    }

    /**
     * Sets a `CHECK` constraint for the column.
     * @param string $check the SQL of the `CHECK` constraint to be added.
     * @return $this
     */
    public function check(string $check): ColumnSchemaBuilder
    {
        $this->check = $check;
        return $this;
    }

    /**
     * Specify the default value for the column.
     * @param mixed $default the default value.
     * @return $this
     */
    public function defaultValue($default): ColumnSchemaBuilder
    {
        if ($default === null) {
            $this->null();
        }

        $this->default = $default;
        return $this;
    }

    /**
     * Specifies the comment for column.
     * @param string $comment the comment
     * @return $this
     * @since 2.0.8
     */
    public function comment(string $comment): ColumnSchemaBuilder
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Marks column as unsigned.
     * @return $this
     * @since 2.0.7
     */
    public function unsigned(): ColumnSchemaBuilder
    {
        switch ($this->type) {
            case Schema::TYPE_PK:
                $this->type = Schema::TYPE_UPK;
                break;
            case Schema::TYPE_BIGPK:
                $this->type = Schema::TYPE_UBIGPK;
                break;
        }
        $this->isUnsigned = true;
        return $this;
    }

    /**
     * Adds an `AFTER` constraint to the column.
     * Note: MySQL, Oracle and Cubrid support only.
     * @param string $after the column after which $this column will be added.
     * @return $this
     * @since 2.0.8
     */
    public function after(string $after): ColumnSchemaBuilder
    {
        $this->after = $after;
        return $this;
    }

    /**
     * Adds an `FIRST` constraint to the column.
     * Note: MySQL, Oracle and Cubrid support only.
     * @return $this
     * @since 2.0.8
     */
    public function first(): ColumnSchemaBuilder
    {
        $this->isFirst = true;
        return $this;
    }

    /**
     * Specify the default SQL expression for the column.
     * @param string $default the default value expression.
     * @return $this
     * @since 2.0.7
     */
    public function defaultExpression(string $default): ColumnSchemaBuilder
    {
        $this->default = new Expression($default);
        return $this;
    }

    /**
     * Specify additional SQL to be appended to column definition.
     * Position modifiers will be appended after column definition in databases that support them.
     * @param string $sql the SQL string to be appended.
     * @return $this
     * @since 2.0.9
     */
    public function append(string $sql): ColumnSchemaBuilder
    {
        $this->append = $sql;
        return $this;
    }

    /**
     * Builds the full string for the column's schema.
     * @return string
     */
    public function __toString()
    {
        switch ($this->getTypeCategory()) {
            case self::CATEGORY_PK:
                $format = '{type}{check}{comment}{append}';
                break;
            default:
                $format = '{type}{length}{notnull}{unique}{default}{check}{comment}{append}';
        }

        return $this->buildCompleteString($format);
    }

    /**
     * Builds the length/precision part of the column.
     * @return string
     */
    protected function buildLengthString(): string
    {
        if ($this->length === null || $this->length === []) {
            return '';
        }
        if (is_array($this->length)) {
            $this->length = implode(',', $this->length);
        }

        return "({$this->length})";
    }

    /**
     * Builds the not null constraint for the column.
     * @return string returns 'NOT NULL' if [[isNotNull]] is true,
     * 'NULL' if [[isNotNull]] is false or an empty string otherwise.
     */
    protected function buildNotNullString(): string
    {
        if ($this->isNotNull === true) {
            return ' NOT NULL';
        } elseif ($this->isNotNull === false) {
            return ' NULL';
        }

        return '';
    }

    /**
     * Builds the unique constraint for the column.
     * @return string returns string 'UNIQUE' if [[isUnique]] is true, otherwise it returns an empty string.
     */
    protected function buildUniqueString(): string
    {
        return $this->isUnique ? ' UNIQUE' : '';
    }

    /**
     * Builds the default value specification for the column.
     * @return string string with default value of column.
     */
    protected function buildDefaultString(): string
    {
        if ($this->default === null) {
            return $this->isNotNull === false ? ' DEFAULT NULL' : '';
        }

        $string = ' DEFAULT ';
        switch (gettype($this->default)) {
            case 'object':
            case 'integer':
                $string .= (string) $this->default;
                break;

            case 'double':
                // ensure type cast always has . as decimal separator in all locales
                $string .= StringHelper::floatToString($this->default);
                break;

            case 'boolean':
                $string .= $this->default ? 'TRUE' : 'FALSE';
                break;

            default:
                $string .= "'{$this->default}'";
        }

        return $string;
    }

    /**
     * Builds the check constraint for the column.
     * @return string a string containing the CHECK constraint.
     */
    protected function buildCheckString(): string
    {
        return $this->check !== null ? " CHECK ({$this->check})" : '';
    }

    /**
     * Builds the unsigned string for column. Defaults to unsupported.
     * @return string a string containing UNSIGNED keyword.
     * @since 2.0.7
     */
    protected function buildUnsignedString(): string
    {
        return '';
    }

    /**
     * Builds the after constraint for the column. Defaults to unsupported.
     * @return string a string containing the AFTER constraint.
     * @since 2.0.8
     */
    protected function buildAfterString(): string
    {
        return '';
    }

    /**
     * Builds the first constraint for the column. Defaults to unsupported.
     * @return string a string containing the FIRST constraint.
     * @since 2.0.8
     */
    protected function buildFirstString(): string
    {
        return '';
    }

    /**
     * Builds the custom string that's appended to column definition.
     * @return string custom string to append.
     * @since 2.0.9
     */
    protected function buildAppendString(): string
    {
        return $this->append !== null ? ' ' . $this->append : '';
    }

    /**
     * Returns the category of the column type.
     * @return string a string containing the column type category name.
     * @since 2.0.8
     */
    protected function getTypeCategory(): ?string
    {
        return $this->categoryMap[$this->type] ?? null;
    }

    /**
     * Builds the comment specification for the column.
     * @return string a string containing the COMMENT keyword and the comment itself
     * @since 2.0.8
     */
    protected function buildCommentString(): string
    {
        return '';
    }

    /**
     * Returns the complete column definition from input format.
     * @param string $format the format of the definition.
     * @return string a string containing the complete column definition.
     * @since 2.0.8
     */
    protected function buildCompleteString(string $format): string
    {
        $placeholderValues = [
            '{type}' => $this->type,
            '{length}' => $this->buildLengthString(),
            '{unsigned}' => $this->buildUnsignedString(),
            '{notnull}' => $this->buildNotNullString(),
            '{unique}' => $this->buildUniqueString(),
            '{default}' => $this->buildDefaultString(),
            '{check}' => $this->buildCheckString(),
            '{comment}' => $this->buildCommentString(),
            '{pos}' => $this->isFirst ? $this->buildFirstString() : $this->buildAfterString(),
            '{append}' => $this->buildAppendString(),
        ];
        return strtr($format, $placeholderValues);
    }
}
