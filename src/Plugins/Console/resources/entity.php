<?php
/**
 * This is the template for generating the model class of a specified table.
 */

/* @var $this Yew\Framework\Web\View */
/* @var $generator Yew\Framework\Generator\Model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */
/* @var $queryClassName string query class name */
/* @var $tableSchema Yew\Framework\Db\TableSchema */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $relations array list of relations (name => relation declaration) */

echo "<?php\n";
?>

namespace <?= $generator->ns ?>;

use Yew\Yew;

/**
* This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
*
<?php foreach ($tableSchema->columns as $column): ?>
    * @property <?= "{$column->phpType} \${$column->name}\n" ?>
<?php endforeach; ?>
<?php if (!empty($relations)): ?>
    *
    <?php foreach ($relations as $name => $relation): ?>
        * @property <?= $relation[1] . ($relation[2] ? '[]' : '') . ' $' . lcfirst($name) . "\n" ?>
    <?php endforeach; ?>
<?php endif; ?>
*/
class <?= $className ?> extends <?= '\\' . ltrim($generator->baseClass, '\\') . "\n" ?>
{
/**
* @inheritdoc
*/
public static function tableName()
{
return '<?= $generator->generateTableName($tableName) ?>';
}
<?php if ($generator->db !== 'db'): ?>

    /**
    * @return \Yew\Framework\Db\Connection the database connection used by this AR class.
    */
    public static function getDb()
    {
    return Yew::$app->get('<?= $generator->db ?>');
    }
<?php endif; ?>

/**
* @inheritdoc
*/
public function rules()
{
return [<?= "\n            " . implode(",\n            ", $rules) . ",\n        " ?>];
}

/**
* @inheritdoc
*/
public function attributeLabels()
{
return [
<?php foreach ($labels as $name => $label): ?>
    <?= "'$name' => " . $generator->generateString($label) . ",\n" ?>
<?php endforeach; ?>
];
}
<?php foreach ($relations as $name => $relation): ?>

    /**
    * @return \Yew\Framework\Db\ActiveQuery
    */
    public function get<?= $name ?>()
    {
    <?= $relation[0] . "\n" ?>
    }
<?php endforeach; ?>
<?php if ($queryClassName): ?>
    <?php
    $queryClassFullName = ($generator->ns === $generator->queryNs) ? $queryClassName : '\\' . $generator->queryNs . '\\' . $queryClassName;
    echo "\n";
    ?>
    /**
    * @inheritdoc
    * @return <?= $queryClassFullName ?> the active query used by this AR class.
    */
    public static function find()
    {
    return new <?= $queryClassFullName ?>(get_called_class());
    }
<?php endif; ?>
}
