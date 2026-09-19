<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    'id' => 'Config',
    'params' => [
        'iconClass' => 'bi bi-gear',

        // Конфигурируемые параметры
        'backend' => [
            'app' => [
                'name' => 'Backend App',
            ],
        ],
        'frontend' => [
            'app' => [
                'name' => 'Frontend App',
                'description' => 'Frontend Meta Description',
                'keywords' => 'Frontend Meta Keywords',
            ],
            'phone' => '+71234567890',
            'email' => 'frontend@test.loc',
        ],
    ],
];
