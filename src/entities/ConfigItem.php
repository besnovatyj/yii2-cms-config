<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\entities;

use Besnovatyj\Contracts\config\OptionItemsProvider;
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
 * @property-read string $module ID модуля-владельца опции (заполняет ConfigCollector)
 * @property-read string $group Подгруппа внутри раздела (необязательный подзаголовок в UI)
 */
class ConfigItem
{
    /** @var array<string, string>|null Варианты выбора, полученные от поставщика (кэш на время запроса) */
    private ?array $resolvedItems = null;

    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly string $label,
        public readonly string $description = '',
        public readonly string $category = '',
        public readonly array $rules = [],
        public readonly array $inputOptions = [],
        public readonly mixed $defaultValue = null,
        public readonly string $module = '',
        public readonly string $group = ''
    ) {
    }

    /**
     * Создает ConfigItem из массива конфигурации
     *
     * @param string $id Идентификатор
     * @param array $config Конфигурация из options.php
     * @param string $module ID модуля, объявившего опцию
     * @return self
     */
    public static function fromArray(string $id, array $config, string $module = ''): self
    {
        return new self(
            id: $id,
            path: $config['path'] ?? '',
            label: $config['label'] ?? $id,
            description: $config['description'] ?? '',
            category: (string)($config['category'] ?? ''),
            rules: $config['rules'] ?? [],
            inputOptions: $config['inputOptions'] ?? ['type' => 'input'],
            defaultValue: $config['default'] ?? self::extractDefaultFromPath($config['path'] ?? ''),
            module: $module,
            group: (string)($config['group'] ?? '')
        );
    }

    /**
     * Ключ раздела, в котором опция показывается в UI и по которому работает
     * deep-link `?category=...`.
     *
     * Приоритет: явная `category` из options.php → ID модуля-владельца → 'app'.
     * Благодаря fallback'у на модуль опции без `category` больше не сваливаются
     * в общую кучу настроек приложения.
     *
     * @return string
     */
    public function groupKey(): string
    {
        if ($this->category !== '') {
            return $this->category;
        }

        return $this->module !== '' ? $this->module : 'app';
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
     * Варианты выбора для поля: либо перечисленные в options.php, либо собранные поставщиком.
     *
     * Поставщик ({@see OptionItemsProvider}) указывается вместо готового списка там, где варианты —
     * это состав системы (установленные ядра поиска, движки редактора), а не фиксированный набор
     * режимов. Он резолвится контейнером в момент обращения, то есть когда приложение уже поднято
     * и знает, что установлено на самом деле.
     *
     * Сбой поставщика не должен ронять всю страницу настроек: пишем в лог и отдаём пустой список —
     * администратор увидит пустое поле, а не белый экран.
     *
     * @return array<string, string> значение => подпись
     */
    public function inputItems(): array
    {
        if ($this->resolvedItems !== null) {
            return $this->resolvedItems;
        }

        $provider = $this->inputOptions['itemsProvider'] ?? null;

        if (!is_string($provider) || $provider === '') {
            return $this->resolvedItems = (array)ArrayHelper::getValue($this->inputOptions, 'items', []);
        }

        try {
            $instance = Yii::$container->get($provider);

            if (!$instance instanceof OptionItemsProvider) {
                Yii::error(
                    "Поставщик вариантов '{$provider}' для опции '{$this->id}' не реализует "
                    . OptionItemsProvider::class,
                    __METHOD__,
                );

                return $this->resolvedItems = [];
            }

            return $this->resolvedItems = $instance->items();
        } catch (\Throwable $e) {
            Yii::error(
                "Не удалось получить варианты опции '{$this->id}' у '{$provider}': {$e->getMessage()}",
                __METHOD__,
            );

            return $this->resolvedItems = [];
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
        $rules = $this->effectiveRules();

        if (empty($rules)) {
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
        foreach ($rules as $rule) {
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
     * Правила валидации с учётом поставщика вариантов.
     *
     * Когда варианты приходят от {@see OptionItemsProvider}, правило «значение из списка»
     * добавляется само: писать `['in', 'range' => ...]` руками было бы вторым списком, который
     * рано или поздно разойдётся с первым. Для опций с обычным `items` поведение не меняется —
     * там список задан вручную, и правило тоже остаётся на совести автора опции.
     *
     * @return array Правила в формате options.php
     */
    private function effectiveRules(): array
    {
        if (!isset($this->inputOptions['itemsProvider'])) {
            return $this->rules;
        }

        $items = $this->inputItems();

        if ($items === []) {
            return $this->rules;
        }

        return array_merge($this->rules, [['in', 'range' => array_keys($items)]]);
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
            'module' => $this->module,
            'group' => $this->group,
            'rules' => $this->rules,
            'inputOptions' => $this->inputOptions,
            'value' => $value ?? $this->defaultValue,
        ];
    }
}
