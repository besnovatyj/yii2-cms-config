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
    // v2 — в кэше лежат сериализованные ConfigItem; при изменении их состава ключ обновляется,
    // иначе после деплоя в приложение попадут объекты старой формы.
    private const string CACHE_KEY = 'config_module_cache_v2';
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
     * Ключ 'overridden' говорит, задано ли значение вручную (иначе действует дефолт из конфига).
     *
     * @return array [id => ['item' => ConfigItem, 'value' => mixed, 'overridden' => bool]]
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
                'overridden' => array_key_exists($id, $values),
            ];
        }

        return $result;
    }

    /**
     * Группирует элементы конфигурации по разделам для отображения в UI.
     *
     * Раздел определяется ConfigItem::groupKey() (категория опции либо ID модуля).
     * Раздел 'app' всегда идёт первым, остальные — по алфавиту.
     *
     * @param array|null $items Результат getAllWithValues(); null — получить самостоятельно
     * @return array<string, array{key: string, label: string, total: int, overridden: int, items: array}>
     */
    public function groupWithValues(?array $items = null): array
    {
        $items ??= $this->getAllWithValues();

        $groups = [];
        foreach ($items as $id => $data) {
            /** @var \Besnovatyj\Config\entities\ConfigItem $item */
            $item = $data['item'];
            $key = $item->groupKey();

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $key === 'app' ? 'Приложение' : $key,
                    'total' => 0,
                    'overridden' => 0,
                    'items' => [],
                ];
            }

            $groups[$key]['items'][$id] = $data;
            $groups[$key]['total']++;

            if (!empty($data['overridden'])) {
                $groups[$key]['overridden']++;
            }
        }

        uksort($groups, static function (int|string $a, int|string $b): int {
            if ($a === 'app' || $b === 'app') {
                return $a === 'app' ? -1 : 1;
            }

            return strnatcasecmp((string)$a, (string)$b);
        });

        return $groups;
    }

    /**
     * Определяет раздел, который надо открыть по параметру `?category=...`.
     *
     * Принимает как ключ раздела, так и ID модуля: менеджер модулей ссылается на
     * настройки по ID, а раздел может называться иначе (или отсутствовать).
     *
     * @param array $groups Результат groupWithValues()
     * @param string $category Значение из запроса
     * @return string Ключ раздела ('' — показать все разделы)
     */
    public function resolveGroupKey(array $groups, string $category): string
    {
        if ($category === '') {
            return '';
        }

        if (isset($groups[$category])) {
            return $category;
        }

        foreach ($groups as $key => $group) {
            foreach ($group['items'] as $data) {
                if ($data['item']->module === $category) {
                    return (string)$key;
                }
            }
        }

        return '';
    }

    /**
     * Сохраняет значения конфигурации.
     *
     * Переданные значения дописываются к уже сохранённым: форма может присылать
     * только часть параметров (один раздел), и это не должно стирать остальные.
     *
     * @param array $values Массив [id => value]
     * @return array ['success' => bool, 'errors' => array]
     */
    public function saveValues(array $values): array
    {
        // Собираем элементы для валидации
        $items = $this->collector->collectItems();
        $errors = [];
        $known = [];

        // Валидируем каждое значение
        foreach ($values as $id => $value) {
            if (!isset($items[$id])) {
                // Пропускаем неизвестные параметры
                continue;
            }

            $known[$id] = $value;

            $itemErrors = $items[$id]->validate($value);
            if (!empty($itemErrors)) {
                $errors[$id] = $itemErrors;
            }
        }

        // Если есть ошибки валидации - не сохраняем
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Сохраняем в хранилище, не теряя параметры, которых не было в форме
        $success = $this->repository->saveValues($known + $this->repository->getValues());

        if ($success) {
            // Очищаем кэш чтобы изменения применились
            $this->getCache()->delete(self::CACHE_KEY);
        }

        return ['success' => $success, 'errors' => []];
    }

    /**
     * Восстанавливает значения по умолчанию.
     *
     * Без аргумента очищает всё хранилище; с указанным разделом удаляет только
     * значения параметров этого раздела (см. ConfigItem::groupKey()).
     *
     * @param string $group Ключ раздела ('' — все разделы)
     * @return bool
     */
    public function restoreDefaults(string $group = ''): bool
    {
        if ($group === '') {
            $success = $this->repository->clearValues();
        } else {
            $values = $this->repository->getValues();

            foreach ($this->collector->collectItems() as $id => $item) {
                if ($item->groupKey() === $group) {
                    unset($values[$id]);
                }
            }

            $success = $values === []
                ? $this->repository->clearValues()
                : $this->repository->saveValues($values);
        }

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
