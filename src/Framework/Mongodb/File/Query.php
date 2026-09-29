<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb\File;

use Yew\Yew;
use Yew\Framework\Mongodb\Connection;

/**
 * Query represents Mongo "find" operation for GridFS collection.
 *
 * Query behaves exactly as regular [[\Yew\Framework\Mongodb\Query]].
 * Found files will be represented as arrays of file document attributes with
 * additional 'file' key, which stores [[\MongoGridFSFile]] instance.
 *
 * @property-read Collection $collection Collection instance.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Query extends \Yew\Framework\Mongodb\Query
{
    /**
     * Returns the Mongo collection for this query.
     * @param \Yew\Framework\Mongodb\Connection $db Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection(?Connection $db = null): \Yew\Framework\Mongodb\Collection
    {
        if ($db === null) {
            $db = Yew::$app->get('mongodb');
        }

        return $db->getFileCollection($this->from);
    }
}
