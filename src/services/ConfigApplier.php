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
     * - 'modules.user.params.passwordResetTokenExpire' -> Yii::$app->getModule('User')->params['passwordResetTokenExpire']
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

        if (!Yii::$app->hasModule($moduleId)) {
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
            $this->applyToParams($moduleId, $parts, $value);
        } else {
            // Для других секций (components, etc) - можно расширить в будущем
            Yii::warning("Unsupported section '{$section}' in path: '{$path}'", __METHOD__);
        }
    }

    /**
     * Применяет значение к params модуля — не загружая модуль ради этого.
     *
     * Applier работает в bootstrap на каждом запросе, поэтому `getModule($id)` здесь означал бы
     * «инстанцировать каждый модуль, у которого есть хоть одна сохранённая опция» (на фронте — все
     * админские модули с настройками). Вместо этого:
     *  - модуль уже загружен (`getModule($id, false)` вернул экземпляр) — пишем в его `params`;
     *  - иначе дописываем `params` в *определение* модуля (`getModules(false)[$id]`) и возвращаем его
     *    через `setModule()`. Yii применит определение при первом настоящем `getModule()` — модуль
     *    остаётся ленивым, а значения попадут в него вместе с остальным конфигом.
     *
     * @param string $moduleId ID модуля (зарегистрирован — проверено вызывающим)
     * @param array $pathParts Оставшиеся части пути
     * @param mixed $value Значение
     */
    private function applyToParams(string $moduleId, array $pathParts, mixed $value): void
    {
        $module = Yii::$app->getModule($moduleId, false);

        if ($module !== null) {
            if (!property_exists($module, 'params') || !is_array($module->params)) {
                Yii::warning("Module '" . get_class($module) . "' does not have params array", __METHOD__);
                return;
            }

            $this->setParam($module->params, $pathParts, $value);
            return;
        }

        $definition = Yii::$app->getModules(false)[$moduleId];

        if (is_string($definition)) {
            $definition = ['class' => $definition];
        } elseif (!is_array($definition)) {
            // Объект не-Module или callable-определение: params в него не дописать, не создавая модуль
            Yii::warning("Module '{$moduleId}' definition is not an array; cannot apply params lazily", __METHOD__);
            return;
        }

        $params = $definition['params'] ?? [];
        if (!is_array($params)) {
            Yii::warning("Module '{$moduleId}' definition has non-array params", __METHOD__);
            return;
        }

        $this->setParam($params, $pathParts, $value);
        $definition['params'] = $params;
        Yii::$app->setModule($moduleId, $definition);
    }

    /**
     * Записывает значение по пути в массив params (экземпляра или определения модуля).
     *
     * @param array $params Массив params (по ссылке)
     * @param array $pathParts Части пути внутри params
     * @param mixed $value Значение
     */
    private function setParam(array &$params, array $pathParts, mixed $value): void
    {
        // Если путь простой: ['comments_allowed']
        if (count($pathParts) === 1) {
            $key = $pathParts[0];
            $params[$key] = $value;
            return;
        }

        // Если путь вложенный: ['frontend', 'app', 'name']
        // Используем ArrayHelper::setValue для безопасной установки
        $paramKey = implode('.', $pathParts);

        try {
            ArrayHelper::setValue($params, $paramKey, $value);
        } catch (\Exception $e) {
            Yii::error("Failed to set param '{$paramKey}': {$e->getMessage()}", __METHOD__);
        }
    }
}
