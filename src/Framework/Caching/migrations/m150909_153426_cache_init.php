<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Caching\DbCache;
use Yew\Framework\Db\Migration;

/**
 * Initializes Cache tables.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 2.0.7
 */
class m150909_153426_cache_init extends Migration
{
    /**
     * @return DbCache
     * @throws InvalidConfigException
     */
    protected function getCache()
    {
        $cache = Yew::$app->getCache();
        if (!$cache instanceof DbCache) {
            throw new InvalidConfigException('You should configure "cache" component to use database before executing this migration.');
        }

        return $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $cache = $this->getCache();
        $this->db = $cache->db;

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable($cache->cacheTable, [
            'id' => $this->string(128)->notNull(),
            'expire' => $this->integer(),
            'data' => $this->binary(),
            'PRIMARY KEY ([[id]])',
            ], $tableOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $cache = $this->getCache();
        $this->db = $cache->db;

        $this->dropTable($cache->cacheTable);
    }
}
