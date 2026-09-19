<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

// Все опции должны быть изначально определены при в конфигурации модуля при подключении в приложение.
return [
    'backend_app_name' => [
        'path' => 'modules.Config.params.backend.app.name',
        'label' => 'Человекочитаемое имя backend приложения',
        'description' => 'Не путать с идентификатором backend приложения "app-backend"',
        'group' => 'backend',
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
        'label' => 'Человекочитаемое имя frontend приложения',
        'description' => 'Не путать с идентификатором backend приложения "app-frontend"',
        'group' => 'frontend',
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
        'label' => 'Meta Description',
        'description' => 'SEO описание frontend приложения',
        'group' => 'frontend',
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
        'label' => 'Meta Keywords',
        'description' => 'SEO ключевые слова frontend приложения',
        'group' => 'frontend',
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
        'label' => 'Контактный номер телефона',
        'description' => '',
        'group' => 'frontend',
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
        'label' => 'Контактный e-mail адрес',
        'description' => '',
        'group' => 'frontend',
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
