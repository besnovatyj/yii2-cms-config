<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Config\storage;

use yii\base\Component;

/**
 * Хранилище элементов конфигурации в формате ['param_id' => 'value', 'another_param' => 'another_value', ... ]
 */
abstract class Storage extends Component
{
    /**
     * Сохранение полученных значений
     * @param array $values - ['param_id' => 'value', 'another_param' => 'another_value', ... ]
     * @return bool success.
     */
    abstract public function save(array $values): bool;

    /**
     * Получение значений из хранилища
     * @return array - ['param_id' => 'value', 'another_param' => 'another_value', ... ]
     */
    abstract public function get(): array;

    /**
     * Очистка всех значений
     * @return bool
     */
    abstract public function clear(): bool;

    /**
     * Очистка конкретного элемента конфигурации
     * @param string $id
     * @return bool
     */
    public function clearValue(string $id): bool
    {
        $items = $this->get();
        unset($items[$id]);
        return $this->save($items);
    }
}
