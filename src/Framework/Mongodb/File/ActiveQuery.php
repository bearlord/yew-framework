<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb\File;

use Yew\Framework\Db\ActiveQueryInterface;
use Yew\Framework\Db\ActiveQueryTrait;
use Yew\Framework\Db\ActiveRelationTrait;
use Yew\Framework\Mongodb\Connection;
use Yew\Framework\Db\ConnectionInterface;

/**
 * ActiveQuery represents a Mongo query associated with an file Active Record class.
 *
 * ActiveQuery instances are usually created by [[ActiveRecord::find()]].
 *
 * Because ActiveQuery extends from [[Query]], one can use query methods, such as [[where()]],
 * [[orderBy()]] to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - [[with()]]: list of relations that this query should be performed with.
 * - [[asArray()]]: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $images = ImageFile::find()->with('tags')->asArray()->all();
 * ```
 *
 * @property-read Collection $collection Collection instance.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * @event Event an event that is triggered when the query is initialized via [[init()]].
     */
    const EVENT_INIT = 'init';


    /**
     * Constructor.
     * @param array $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct(array $modelClass = [], $config = [])
    {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init(): void
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * {@inheritdoc}
     */
    public function buildCursor(?Connection $db = null): \MongoDB\Driver\Cursor
    {
        if ($this->primaryModel !== null) {
            // lazy loading
            if ($this->via instanceof self) {
                // via pivot collection
                $viaModels = $this->via->findJunctionRows([$this->primaryModel]);
                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                // via relation
                /* @var ActiveQuery $viaQuery */
                list($viaName, $viaQuery) = $this->via;
                if ($viaQuery->multiple) {
                    $viaModels = $viaQuery->all();
                    $this->primaryModel->populateRelation($viaName, $viaModels);
                } else {
                    $model = $viaQuery->one();
                    $this->primaryModel->populateRelation($viaName, $model);
                    $viaModels = $model === null ? [] : [$model];
                }
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }
        }

        return parent::buildCursor($db);
    }

    /**
     * Executes query and returns all results as an array.
     * @param \Yew\Framework\Mongodb\Connection $db the Mongo connection used to execute the query.
     * If null, the Mongo connection returned by [[modelClass]] will be used.
     * @return array|ActiveRecord the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(?ConnectionInterface $db = null): array
    {
        return parent::all($db);
    }

    /**
     * Executes query and returns a single row of result.
     * @param \Yew\Framework\Mongodb\Connection $db the Mongo connection used to execute the query.
     * If null, the Mongo connection returned by [[modelClass]] will be used.
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     */
    public function one(?ConnectionInterface $db = null): array|false
    {
        $row = parent::one($db);
        if ($row !== false) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        }
        return null;
    }

    /**
     * Returns the Mongo collection for this query.
     * @param \Yew\Framework\Mongodb\Connection $db Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection(?Connection $db = null): \Yew\Framework\Mongodb\Collection
    {
        /* @var ActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        if ($db === null) {
            $db = $modelClass::getDb();
        }
        if ($this->from === null) {
            $this->from = $modelClass::collectionName();
        }

        return $db->getFileCollection($this->from);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from MongoDB
     * into the format as required by this query.
     * @param array $rows the raw query result from MongoDB
     * @return array the converted query result
     */
    public function populate(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $indexBy = $this->indexBy;
        $this->indexBy = null;
        $rows = parent::populate($rows);
        $this->indexBy = $indexBy;

        $models = $this->createModels($rows);

        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }
        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        }

        return parent::populate($models);
    }
}
