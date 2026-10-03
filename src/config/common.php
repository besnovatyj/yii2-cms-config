<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Config\Module;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Объявляется через `extra.config-plugin`, собирается modman в merge-plan и мёржится в рантайме.
 * Содержит регистрацию модуля + bootstrap-классы (L2 — выполняются только у активного модуля, гейт
 * modman). Меню админки — `adminMenu.php` (группа `admin-menu`), миграции — вклад modman. Значения берутся
 * из статических методов {@see Module} — единый источник, без дублирования.
 */
return [
    'modules' => [
        Module::moduleId() => array_merge(
            ['class' => Module::class],
            Module::moduleConfig(),
        ),
    ],
    'bootstrap' => array_values(Module::bootstrapClasses()),
];
