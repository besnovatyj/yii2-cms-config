<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\entities;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * ConfigItem представляет один элемент конфигурации приложения
 * Иммутабельный value object с методами валидации
 *
 * @property-read string $id Уникальный идентификатор параметра
 * @property-read string $path Путь в конфигурации (например 'modules.blog.params.comments_allowed')
 * @property-read string $label Человекочитаемая метка для UI
 * @property-read string $description Описание параметра
 * @property-read string $category Категория для группировки (app, blog, user и т.д.)
 * @property-read array $rules Правила валидации Yii2
 * @property-read array $inputOptions Опции для рендеринга поля ввода
 * @property-read mixed $defaultValue Значение по умолчанию
 */
class ConfigItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly string $label,
        public readonly string $description = '',
        public readonly string $category = 'app',
        public readonly array $rules = [],
        public readonly array $inputOptions = [],
        public readonly mixed $defaultValue = null
    ) {
    }

    /**
     * Создает ConfigItem из массива конфигурации
     *
     * @param string $id Идентификатор
     * @param array $config Конфигурация из options.php
     * @return self
     */
    public static function fromArray(string $id, array $config): self
    {
        return new self(
            id: $id,
            path: $config['path'] ?? '',
            label: $config['label'] ?? $id,
            description: $config['description'] ?? '',
            category: $config['category'] ?? 'app',
            rules: $config['rules'] ?? [],
            inputOptions: $config['inputOptions'] ?? ['type' => 'input'],
            defaultValue: $config['default'] ?? self::extractDefaultFromPath($config['path'] ?? '')
        );
    }

    /**
     * Извлекает значение по умолчанию из текущей конфигурации приложения
     *
     * @param string $path Путь в конфигурации
     * @return mixed
     */
    private static function extractDefaultFromPath(string $path): mixed
    {
        if (empty($path)) {
            return null;
        }

        try {
            $parts = explode('.', $path);
            $target = Yii::$app;

            while (count($parts) > 0) {
                $key = array_shift($parts);

                if ($key === 'modules') {
                    $moduleId = array_shift($parts);
                    $target = Yii::$app->getModule($moduleId);
                    if (!$target) {
                        return null;
                    }
                    continue;
                }

                if ($key === 'params' && is_object($target) && property_exists($target, 'params')) {
                    $paramKey = implode('.', $parts);
                    return ArrayHelper::getValue($target->params, $paramKey);
                }

                if (is_array($target)) {
                    $target = ArrayHelper::getValue($target, $key);
                } elseif (is_object($target)) {
                    $target = $target->$key ?? null;
                } else {
                    return null;
                }

                if ($target === null) {
                    return null;
                }
            }

            return $target;
        } catch (\Exception $e) {
            Yii::warning("Failed to extract default value for path '{$path}': {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Валидирует значение согласно правилам
     *
     * @param mixed $value Значение для валидации
     * @return array Массив ошибок валидации (пустой если ошибок нет)
     */
    public function validate(mixed $value): array
    {
        if (empty($this->rules)) {
            return [];
        }

        // Создаем временную модель для валидации
        $model = new class extends Model {
            public $value;

            public function rules(): array
            {
                return [];
            }
        };

        $model->value = $value;

        // Применяем правила валидации
        $validators = [];
        foreach ($this->rules as $rule) {
            if (is_array($rule) && isset($rule[0])) {
                $validatorType = $rule[0];
                $params = array_slice($rule, 1);
                $validators[] = \yii\validators\Validator::createValidator(
                    $validatorType,
                    $model,
                    ['value'],
                    $params
                );
            }
        }

        $errors = [];
        foreach ($validators as $validator) {
            $validator->validateAttribute($model, 'value');
        }

        if ($model->hasErrors('value')) {
            $errors = $model->getErrors('value');
        }

        return $errors;
    }

    /**
     * Создает копию с новым значением (для работы с формами)
     *
     * @param mixed $value Новое значение
     * @return array
     */
    public function toArray(mixed $value = null): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'label' => $this->label,
            'description' => $this->description,
            'category' => $this->category,
            'rules' => $this->rules,
            'inputOptions' => $this->inputOptions,
            'value' => $value ?? $this->defaultValue,
        ];
    }
}
