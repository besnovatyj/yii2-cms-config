<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    'id' => 'Config',
    'params' => [
        'iconClass' => 'bi bi-gear',

        'directories' => false, // Если для работы модуля необходимы директории для статики

        // Конфигурируемые параметры
        'backend' => [
            'app' => [
                'name' => 'backend-app',
            ],
        ],
        'frontend' => [
            'app' => [
                'name' => 'frontend-app',
                'description' => 'Frontend Meta Description',
                'keywords' => 'Frontend Meta Keywords',
            ],
            'phone' => '+71234567890',
            'email' => 'frontend@test.loc',
        ],
    ],
];
