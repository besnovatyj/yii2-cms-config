<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\services;

use Besnovatyj\Config\entities\ConfigItem;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * ConfigApplier - применяет конфигурацию к приложению и модулям
 */
class ConfigApplier
{
    /**
     * Применяет сохраненные значения к приложению
     *
     * @param ConfigItem[] $items Элементы конфигурации
     * @param array $values Сохраненные значения [id => value]
     */
    public function apply(array $items, array $values): void
    {
        foreach ($items as $item) {
            if (!isset($values[$item->id])) {
                continue; // Нет сохраненного значения, используем дефолтное
            }

            $value = $values[$item->id];
            $this->applyValue($item->path, $value);
        }
    }

    /**
     * Применяет одно значение по пути
     *
     * Примеры путей:
     * - 'modules.blog.params.comments_allowed' -> Yii::$app->getModule('blog')->params['comments_allowed']
     * - 'modules.user.params.passwordResetTokenExpire' -> Yii::$app->getModule('user')->params['passwordResetTokenExpire']
     * - 'modules.Config.params.frontend.app.name' -> Yii::$app->getModule('Config')->params['frontend']['app']['name']
     *
     * @param string $path Путь в конфигурации
     * @param mixed $value Значение для установки
     */
    private function applyValue(string $path, mixed $value): void
    {
        if (empty($path)) {
            return;
        }

        $parts = explode('.', $path);

        if (count($parts) < 2) {
            Yii::warning("Invalid config path: '{$path}'", __METHOD__);
            return;
        }

        // Первая часть должна быть 'modules'
        $firstPart = array_shift($parts);

        if ($firstPart !== 'modules') {
            Yii::warning("Config path must start with 'modules': '{$path}'", __METHOD__);
            return;
        }

        // Вторая часть - ID модуля
        $moduleId = array_shift($parts);
        $module = Yii::$app->getModule($moduleId);

        if ($module === null) {
            Yii::warning("Module '{$moduleId}' not found for path: '{$path}'", __METHOD__);
            return;
        }

        // Третья часть - обычно 'params'
        if (count($parts) === 0) {
            Yii::warning("Path too short: '{$path}'", __METHOD__);
            return;
        }

        $section = array_shift($parts);

        if ($section === 'params') {
            // Применяем к params модуля
            $this->applyToParams($module, $parts, $value);
        } else {
            // Для других секций (components, etc) - можно расширить в будущем
            Yii::warning("Unsupported section '{$section}' in path: '{$path}'", __METHOD__);
        }
    }

    /**
     * Применяет значение к params модуля
     *
     * @param object $module Модуль
     * @param array $pathParts Оставшиеся части пути
     * @param mixed $value Значение
     */
    private function applyToParams(object $module, array $pathParts, mixed $value): void
    {
        if (!property_exists($module, 'params') || !is_array($module->params)) {
            Yii::warning("Module '" . get_class($module) . "' does not have params array", __METHOD__);
            return;
        }

        // Если путь простой: ['comments_allowed']
        if (count($pathParts) === 1) {
            $key = $pathParts[0];
            $module->params[$key] = $value;
            return;
        }

        // Если путь вложенный: ['frontend', 'app', 'name']
        // Используем ArrayHelper::setValue для безопасной установки
        $paramKey = implode('.', $pathParts);

        try {
            ArrayHelper::setValue($module->params, $paramKey, $value);
        } catch (\Exception $e) {
            Yii::error("Failed to set param '{$paramKey}': {$e->getMessage()}", __METHOD__);
        }
    }
}
