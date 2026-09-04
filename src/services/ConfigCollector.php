<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\services;

use Besnovatyj\Config\entities\ConfigItem;
use Yii;

/**
 * ConfigCollector - собирает опции конфигурации из всех установленных модулей.
 *
 * Получает опции через статический метод модуля, поддерживая два контракта:
 *  - legacy `Module::getOptions()` (прежний патч-modman);
 *  - новый `ProvidesOptions::options()` (compile-not-patch modman).
 * Это позволяет работать с модулями из любых директорий (app/modules и composer vendor).
 */
class ConfigCollector
{
    /**
     * Контракт нового modman, объявляющий настраиваемые опции модуля.
     * Ссылаемся строкой, чтобы не зависеть жёстко от установленного менеджера модулей:
     * если контракт недоступен, is_subclass_of() просто вернёт false.
     */
    private const OPTIONS_CONTRACT = \Besnovatyj\Contracts\module\ProvidesOptions::class;


    /**
     * Собирает все элементы конфигурации из модулей приложения
     *
     * @return ConfigItem[] Массив [id => ConfigItem]
     */
    public function collectItems(): array
    {
        $items = [];

        // Получаем все зарегистрированные модули
        $modules = Yii::$app->getModules(false);

        foreach ($modules as $moduleId => $moduleConfig) {
            $moduleItems = $this->collectModuleItems($moduleId, $moduleConfig);
            $items = array_merge($items, $moduleItems);
        }

        return $items;
    }

    /**
     * Собирает опции из одного модуля
     *
     * @param string $moduleId ID модуля
     * @param mixed $moduleConfig Конфигурация модуля (массив или объект)
     * @return ConfigItem[]
     */
    private function collectModuleItems(string $moduleId, mixed $moduleConfig): array
    {
        $className = $this->getModuleClassName($moduleConfig);

        if ($className === null) {
            return [];
        }

        $options = $this->resolveModuleOptions($moduleId, $className);
        if ($options === null) {
            return [];
        }

        return $this->createItemsFromArray($options, $moduleId);
    }

    /**
     * Получает массив опций модуля из доступного контракта (legacy getOptions() или нового options()).
     *
     * @param string $moduleId  ID модуля (для диагностики)
     * @param string $className Имя класса модуля
     * @return array<int|string, mixed>|null null — модуль опций не предоставляет или вернул не-массив
     */
    private function resolveModuleOptions(string $moduleId, string $className): ?array
    {
        $method = match (true) {
            method_exists($className, 'getOptions') => 'getOptions',
            is_subclass_of($className, self::OPTIONS_CONTRACT) => 'options',
            default => null,
        };

        if ($method === null) {
            return null;
        }

        try {
            $options = $className::$method();
        } catch (\Throwable $e) {
            Yii::error("Failed to get options from module '{$moduleId}': {$e->getMessage()}", __METHOD__);
            return null;
        }

        if (!is_array($options)) {
            Yii::warning("{$method}() for module '{$moduleId}' did not return an array", __METHOD__);
            return null;
        }

        return $options;
    }

    /**
     * Получает имя класса модуля из конфигурации
     *
     * @param mixed $moduleConfig
     * @return string|null
     */
    private function getModuleClassName(mixed $moduleConfig): ?string
    {
        if (is_string($moduleConfig)) {
            return $moduleConfig;
        }

        if (is_array($moduleConfig) && isset($moduleConfig['class'])) {
            return $moduleConfig['class'];
        }

        if (is_object($moduleConfig)) {
            return get_class($moduleConfig);
        }

        return null;
    }

    /**
     * Получает путь к файлу options.php модуля
     *
     * @param string $className Имя класса модуля
     * @return string
     */
    private function getModuleOptionsFile(string $className): string
    {
        // Преобразуем namespace в путь: modules\blog\Module -> modules/blog
        $classPath = str_replace('\\', '/', $className);
        $classPath = str_replace('/Module', '', $classPath);

        // Строим путь к options.php
        return Yii::getAlias('@root/' . $classPath . '/config/options.php');
    }

    /**
     * Создает ConfigItem объекты из массива опций
     *
     * @param array $options Массив опций из options.php
     * @param string $moduleId ID модуля-владельца (используется для группировки в UI)
     * @return ConfigItem[]
     */
    private function createItemsFromArray(array $options, string $moduleId = ''): array
    {
        $items = [];

        foreach ($options as $id => $config) {
            if (!is_array($config)) {
                Yii::warning("Invalid config format for option '{$id}'", __METHOD__);
                continue;
            }

            try {
                $items[$id] = ConfigItem::fromArray($id, $config, $moduleId);
            } catch (\Exception $e) {
                Yii::error("Failed to create ConfigItem for '{$id}': {$e->getMessage()}", __METHOD__);
            }
        }

        return $items;
    }
}
