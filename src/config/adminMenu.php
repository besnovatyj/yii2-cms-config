<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [[
    'label' => 'Config',
    'iconClass' => 'bi bi-gear me-1',
    'url' => ['/Config/backend/config/index'],
    'active' => static function () {
        return str_contains(\Yii::$app->request->url, 'Config/backend/config');
    },
    '_meta' => [
        'placements' => [
            [
                'location' => 'right-sidebar',
                'group' => 'Service',
                'groupIcon' => 'bi bi-sliders',
                'priority' => 100,
                'groupPriority' => 100,
            ],
        ],
    ],
]];
