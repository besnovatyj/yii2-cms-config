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
 * Использует статический метод Module::getOptions() для получения опций,
 * что позволяет работать с модулями из любых директорий (app/modules и composer vendor).
 * Fallback: сканирует файлы config/options.php для модулей без метода getOptions().
 */
class ConfigCollector
{
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

        if (method_exists($className, 'getOptions')) {
            try {
                $options = $className::getOptions();

                if (!is_array($options)) {
                    Yii::warning("getOptions() for module '{$moduleId}' did not return an array", __METHOD__);
                    return [];
                }

                return $this->createItemsFromArray($options);
            } catch (\Exception $e) {
                Yii::error("Failed to get options from module '{$moduleId}': {$e->getMessage()}", __METHOD__);
                return [];
            }
        }
        return [];
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
     * @return ConfigItem[]
     */
    private function createItemsFromArray(array $options): array
    {
        $items = [];

        foreach ($options as $id => $config) {
            if (!is_array($config)) {
                Yii::warning("Invalid config format for option '{$id}'", __METHOD__);
                continue;
            }

            try {
                $items[$id] = ConfigItem::fromArray($id, $config);
            } catch (\Exception $e) {
                Yii::error("Failed to create ConfigItem for '{$id}': {$e->getMessage()}", __METHOD__);
            }
        }

        return $items;
    }
}
