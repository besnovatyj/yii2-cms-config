<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

// Все опции должны быть изначально определены при в конфигурации модуля при подключении в приложение.
return [
    'backend_app_name' => [
        'path' => 'modules.Config.params.backend.app.name',
        'label' => 'Backend application name',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'backend\'][\'app\'][\'name\']',
        'category' => 'app',
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'frontend_app_name' => [
        'path' => 'modules.Config.params.frontend.app.name',
        'label' => 'Frontend application name',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'frontend\'][\'app\'][\'name\']',
        'category' => 'app',
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'frontend_app_desc' => [
        'path' => 'modules.Config.params.frontend.app.description',
        'label' => 'Frontend application description',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'frontend\'][\'app\'][\'description\']',
        'category' => 'app',
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'frontend_app_keywords' => [
        'path' => 'modules.Config.params.frontend.app.keywords',
        'label' => 'Frontend application keywords',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'frontend\'][\'app\'][\'keywords\']',
        'category' => 'app',
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'frontend_phone' => [
        'path' => 'modules.Config.params.frontend.phone',
        'label' => 'Contact phone number',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'frontend\'][\'phone\']',
        'category' => 'app',
        'rules' => [
            ['string']
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
    'frontend_email' => [
        'path' => 'modules.Config.params.frontend.email',
        'label' => 'Contact e-mail address',
        'description' => 'Yii::$app->getModule(\'config\')->params[\'frontend\'][\'email\']',
        'category' => 'app',
        'rules' => [
            ['string'],
            ['email'],
        ],
        'inputOptions' => [
            'type' => 'input',
        ],
    ],
];
