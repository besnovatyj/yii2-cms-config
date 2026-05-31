<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\storage;

/**
 * Интерфейс для хранилищ конфигурации.
 * Позволяет легко переключаться между разными способами хранения (файл, БД, Redis и т.д.)
 */
interface StorageInterface
{
    /**
     * Получить значение одного параметра
     *
     * @param string $id Идентификатор параметра
     * @return string|null Значение или null если не найдено
     */
    public function get(string $id): ?string;

    /**
     * Получить все сохраненные значения
     *
     * @return array Массив [id => value]
     */
    public function getAll(): array;

    /**
     * Сохранить значения параметров
     *
     * @param array $values Массив [id => value]
     * @return bool Успешность операции
     */
    public function save(array $values): bool;

    /**
     * Очистить все сохраненные значения
     * После очистки будут использоваться значения по умолчанию
     *
     * @return bool Успешность операции
     */
    public function clear(): bool;
}
