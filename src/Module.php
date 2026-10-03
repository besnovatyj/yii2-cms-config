<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config;

use Besnovatyj\Kernel\module\CmsModule;
use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Contracts\module\ProvidesBootstrap;
use Besnovatyj\Contracts\module\ProvidesOptions;

/**
 * Config Module - управление динамической конфигурацией приложения
 *
 * Архитектура:
 * - Entity Layer: ConfigItem (иммутабельный value object)
 * - Storage Layer: StorageInterface с реализациями (PHP файл, БД)
 * - Repository Layer: ConfigRepository
 * - Service Layer: ConfigCollector, ConfigApplier, ConfigService
 * - Controller Layer: Тонкий контроллер с DI
 */
class Module extends CmsModule implements
    DeclaresModule, 
    ProvidesBootstrap, ProvidesOptions
{
    public const bool EDITABLE = true;
    public const string MODULE_ID = 'Config';

    public static function moduleId(): string { return self::MODULE_ID; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function moduleConfig(): array { return require __DIR__.'/config/config.php'; }
    public static function options(): array { return require __DIR__.'/config/options.php'; }
    public static function bootstrapClasses(): array { return [Bootstrap::class]; }

}
