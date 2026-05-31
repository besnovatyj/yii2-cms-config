<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\repositories;

use Besnovatyj\Config\storage\StorageInterface;

/**
 * ConfigRepository - слой абстракции над хранилищем конфигурации
 * Инкапсулирует работу с хранилищем, позволяя легко его заменить
 */
readonly class ConfigRepository
{
    /**
     * @param StorageInterface $storage Реализация хранилища (PHP файл, БД, Redis и т.д.)
     */
    public function __construct(
        private StorageInterface $storage
    ) {
    }

    /**
     * Получить значение одного параметра
     *
     * @param string $id Идентификатор параметра
     * @return string|null
     */
    public function getValue(string $id): ?string
    {
        return $this->storage->get($id);
    }

    /**
     * Получить все сохраненные значения
     *
     * @return array Массив [id => value]
     */
    public function getValues(): array
    {
        return $this->storage->getAll();
    }

    /**
     * Сохранить значения параметров
     *
     * @param array $values Массив [id => value]
     * @return bool
     */
    public function saveValues(array $values): bool
    {
        return $this->storage->save($values);
    }

    /**
     * Очистить все сохраненные значения
     * После очистки будут использоваться значения по умолчанию
     *
     * @return bool
     */
    public function clearValues(): bool
    {
        return $this->storage->clear();
    }
}
