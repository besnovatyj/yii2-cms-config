<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\entities;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * ConfigValue ActiveRecord для хранения значений конфигурации в БД
 *
 * @property string $id Идентификатор параметра (primary key)
 * @property string $value Значение параметра
 * @property int $updated_at Время последнего обновления
 * @property int|null $updated_by ID пользователя, который обновил
 */
class ConfigValue extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%config_values}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => false, // Нет created_at
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['id', 'value'], 'required'],
            [['id'], 'string', 'max' => 255],
            [['value'], 'string'],
            [['updated_by'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'Parameter ID',
            'value' => 'Value',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
        ];
    }
}
