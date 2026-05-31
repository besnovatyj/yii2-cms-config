<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\storage;

use Besnovatyj\Config\entities\ConfigValue;
use Yii;
use yii\db\Exception;

/**
 * DatabaseStorage - хранение конфигурации в базе данных.
 * Подходит для:
 * - Enterprise проектов
 * - Мультитенантных систем
 * - Когда нужна история изменений (можно расширить)
 * - Когда нужен аудит
 */
class DatabaseStorage implements StorageInterface
{
    /**
     * @param string $tableName Имя таблицы (по умолчанию config_values)
     */
    public function __construct(
        private string $tableName = '{{%config_values}}'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): ?string
    {
        $model = ConfigValue::findOne($id);
        return $model?->value;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        $models = ConfigValue::find()->all();

        $result = [];
        foreach ($models as $model) {
            $result[$model->id] = $model->value;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function save(array $values): bool
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($values as $id => $value) {
                $model = ConfigValue::findOne($id);

                if ($model === null) {
                    $model = new ConfigValue();
                    $model->id = $id;
                }

                $model->value = (string)$value;

                // Устанавливаем updated_by если пользователь авторизован
                if (!Yii::$app->user->isGuest) {
                    $model->updated_by = Yii::$app->user->id;
                }

                if (!$model->save()) {
                    throw new Exception('Failed to save config value: ' . $id);
                }
            }

            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("Failed to save config values: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        try {
            ConfigValue::deleteAll();
            return true;
        } catch (\Exception $e) {
            Yii::error("Failed to clear config values: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
}
