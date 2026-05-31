<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/**
 * Схема БД для модуля конфигурации
 *
 * Таблица config_values используется только при выборе DatabaseStorage.
 * По умолчанию используется PhpFileStorage (не требует БД).
 *
 * Чтобы переключиться на DatabaseStorage:
 * 1. Измените config/container.php - замените PhpFileStorage на DatabaseStorage
 * 2. Запустите миграции модуля для создания таблицы
 */
return [
    'tables' => [
        // https://www.yiiframework.com/doc/api/2.0/yii-db-querybuilder#getColumnType()-detail
        // @see \yii\db\QueryBuilder::getColumnType()
        '{{%config_values}}' => [
            'columns' => [
                'id' => 'string(255) NOT NULL PRIMARY KEY',
                'value' => 'text NOT NULL',
                'updated_at' => 'integer NOT NULL',
                'updated_by' => 'integer',
            ],
            'comments' => [
                'id' => 'Идентификатор параметра конфигурации',
                'value' => 'Значение параметра',
                'updated_at' => 'Время последнего обновления (timestamp)',
                'updated_by' => 'ID пользователя который обновил',
            ],
            'comment' => 'Хранение значений конфигурации приложения',
            'indexes' => [
                ['updated_at'],
            ],
            'foreignKeys' => [
                [['updated_by'], '{{%user_users}}', ['id'], 'SET NULL', 'CASCADE'],
            ],
        ],
    ],
];
