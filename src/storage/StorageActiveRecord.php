<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Config\storage;

use yii\db\ActiveRecordInterface;

/**
 * Хранилище ActiveRecord.
 *
 * You may use same ActiveRecord class for multiple configuration storage providing [[filter]] value.
 *
 * @see ActiveRecordInterface
 */
class StorageActiveRecord extends Storage
{
    use StorageFilterTrait;

    /**
     * @var string name of the ActiveRecord class, which should be used for data finding and saving.
     * This class should match [[\yii\db\ActiveRecordInterface]] interface.
     */
    public string $activeRecordClass;
    /**
     * @var string name of the attribute, which should store config item ID.
     */
    public string $idAttribute = 'id';
    /**
     * @var string name of the attribute, which should store config item value.
     */
    public string $valueAttribute = 'value';

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        /* @var $activeRecordClass ActiveRecordInterface */
        /* @var $existingRecords ActiveRecordInterface[] */
        $activeRecordClass = $this->activeRecordClass;

        $filterAttributes = $this->composeFilterCondition();

        $existingRecords = $activeRecordClass::find()
            ->andWhere($filterAttributes)
            ->all();

        $result = true;

        foreach ($existingRecords as $key => $existingRecord) {
            if (array_key_exists($existingRecord->{$this->idAttribute}, $values)) {
                $existingRecord->value = $values[$existingRecord->{$this->idAttribute}];
                $result = $result && $existingRecord->save(false);
                unset($values[$existingRecord->{$this->idAttribute}]);
                unset($existingRecords[$key]);
            }
        }

        foreach ($existingRecords as $existingRecord) {
            $existingRecord->delete();
        }

        foreach ($values as $id => $value) {
            $model = new $activeRecordClass();
            $attributes = array_merge($filterAttributes, [$this->idAttribute => $id, $this->valueAttribute => $value]);
            foreach ($attributes as $attributeName => $attributeValue) {
                $model->$attributeName = $attributeValue;
            }
            $result = $result && $model->save(false);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        /* @var $activeRecordClass ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;
        $rows = $activeRecordClass::find()
            ->andWhere($this->composeFilterCondition())
            ->all();

        $values = [];
        foreach ($rows as $row) {
            $values[$row->{$this->idAttribute}] = $row->{$this->valueAttribute};
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        /* @var $activeRecordClass ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;

        $result = true;
        foreach ($activeRecordClass::find()->andWhere($this->composeFilterCondition())->all() as $row) {
            /* @var $row ActiveRecordInterface */
            $result = $result && ($row->delete() > 0);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clearValue(string $id): bool
    {
        /* @var $activeRecordClass ActiveRecordInterface */
        /* @var $row ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;
        $row = $activeRecordClass::find()
            ->andWhere($this->composeFilterCondition([$this->idAttribute => $id]))
            ->one();

        if ($row) {
            return $row->delete() > 0;
        }

        return true;
    }
}
