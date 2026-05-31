<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Config\storage;

use yii\di\Instance;
use yii\mongodb\Connection;
use yii\mongodb\Query;

/**
 * StorageMongoDb represents the configuration storage based on MongoDB collection.
 * This storage requires [yiisoft/yii2-mongodb](https://github.com/yiisoft/yii2-mongodb) extension installed.
 * You may use same collection for multiple configuration storage providing [[filter]] value.
 */
class StorageMongoDb extends Storage
{
    use StorageFilterTrait;

    /**
     * @var Connection|array|string the MongoDB connection object or the application component ID of the MongoDB connection.
     * After the StorageMongoDb object is created, if you want to change this property, you should only assign it
     * with a MongoDB connection object.
     */
    public $db = 'mongodb';
    /**
     * @var string|array name of the collection, which should store values.
     */
    public $collection = 'AppConfig';


    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values):bool
    {
        $this->clear();
        $data = [];
        foreach ($values as $id => $value) {
            $data[] = array_merge(
                $this->composeFilterCondition(),
                [
                    'id' => $id,
                    'value' => $value
                ]
            );
        }
        $this->db->getCollection($this->collection)->batchInsert($data);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        $query = new Query();
        $rows = $query->from($this->collection)
            ->andWhere($this->composeFilterCondition())
            ->all();
        $values = [];
        foreach ($rows as $row) {
            $values[$row['id']] = $row['value'];
        }
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->db->getCollection($this->collection)->remove($this->composeFilterCondition());
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clearValue(string $id): bool
    {
        $this->db->getCollection($this->collection)->remove($this->composeFilterCondition(['id' => $id]));
        return true;
    }
}
