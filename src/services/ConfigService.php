<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\services;

use Besnovatyj\Config\repositories\ConfigRepository;
use Yii;

/**
 * ConfigService - главный сервис для работы с конфигурацией.
 * Оркестрирует ConfigCollector, ConfigRepository, ConfigApplier
 *
 * Все операции выполняются через этот сервис:
 * - Получение элементов со значениями (для UI)
 * - Сохранение значений
 * - Восстановление значений по умолчанию
 * - Применение конфигурации к приложению (в bootstrap)
 */
class ConfigService
{
    private const string CACHE_KEY = 'config_module_cache';
    private const int CACHE_DURATION = 3600; // 1 час

    /**
     * @param ConfigCollector $collector Сборщик опций из модулей
     * @param ConfigRepository $repository Репозиторий для работы с хранилищем
     * @param ConfigApplier $applier Применение конфигурации к приложению
     */
    public function __construct(
        private readonly ConfigCollector  $collector,
        private readonly ConfigRepository $repository,
        private readonly ConfigApplier $applier
    ) {
    }

    /**
     * Получает кэш компонент (ленивая загрузка)
     * Cache может быть не инициализирован во время bootstrap
     */
    private function getCache(): ?\yii\caching\CacheInterface
    {
        return Yii::$app->cache;
    }

    /**
     * Получает все элементы конфигурации с текущими значениями.
     * Используется для отображения в UI.
     *
     * @return array [id => ['item' => ConfigItem, 'value' => mixed]]
     */
    public function getAllWithValues(): array
    {
        $items = $this->collector->collectItems();
        $values = $this->repository->getValues();

        $result = [];
        foreach ($items as $id => $item) {
            $result[$id] = [
                'item' => $item,
                'value' => $values[$id] ?? $item->defaultValue,
            ];
        }

        return $result;
    }

    /**
     * Сохраняет значения конфигурации
     *
     * @param array $values Массив [id => value]
     * @return array ['success' => bool, 'errors' => array]
     */
    public function saveValues(array $values): array
    {
        // Собираем элементы для валидации
        $items = $this->collector->collectItems();
        $errors = [];

        // Валидируем каждое значение
        foreach ($values as $id => $value) {
            if (!isset($items[$id])) {
                // Пропускаем неизвестные параметры
                continue;
            }

            $itemErrors = $items[$id]->validate($value);
            if (!empty($itemErrors)) {
                $errors[$id] = $itemErrors;
            }
        }

        // Если есть ошибки валидации - не сохраняем
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Сохраняем в хранилище
        $success = $this->repository->saveValues($values);

        if ($success) {
            // Очищаем кэш чтобы изменения применились
            $this->getCache()->delete(self::CACHE_KEY);
        }

        return ['success' => $success, 'errors' => []];
    }

    /**
     * Восстанавливает значения по умолчанию.
     * Очищает все сохраненные значения из хранилища.
     *
     * @return bool
     */
    public function restoreDefaults(): bool
    {
        $success = $this->repository->clearValues();

        if ($success) {
            $this->getCache()->delete(self::CACHE_KEY);
        }

        return $success;
    }

    /**
     * Применяет сохраненную конфигурацию к приложению.
     * Вызывается из Bootstrap при загрузке приложения.
     *
     * Использует кэш для производительности.
     */
    public function applyConfiguration(): void
    {
        // Пытаемся получить из кэша
        $cache = $this->getCache();
        $config = $cache->get(self::CACHE_KEY);

        if ($config === false) {
            // Кэш пустой - собираем конфигурацию
            $items = $this->collector->collectItems();
            $values = $this->repository->getValues();

            $config = [
                'items' => $items,
                'values' => $values,
            ];

            // Сохраняем в кэш
            $cache->set(self::CACHE_KEY, $config, self::CACHE_DURATION);
        }

        // Применяем конфигурацию
        $this->applier->apply($config['items'], $config['values']);
    }
}
