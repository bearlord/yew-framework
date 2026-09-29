<?php
/**
 * This view is used by Console/Controllers/MigrateController.php
 * The following variables are available in this view:
 */

/* @var string $className the new migration class name */
echo "<?php\n";
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

class <?= $className ?> extends \Yew\Framework\Mongodb\Migration
{
    public function up()
    {

    }

    public function down()
    {
        echo "<?= $className ?> cannot be reverted.\n";

        return false;
    }
}
