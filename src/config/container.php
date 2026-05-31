<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/**
 * DI Container конфигурация для модуля config
 *
 * Здесь регистрируются все зависимости модуля:
 * - Storage (можно переключать между PhpFileStorage и DatabaseStorage)
 * - Repository
 * - Services (Collector, Applier, ConfigService)
 */

use Besnovatyj\Config\repositories\ConfigRepository;
use Besnovatyj\Config\services\ConfigApplier;
use Besnovatyj\Config\services\ConfigCollector;
use Besnovatyj\Config\services\ConfigService;
use Besnovatyj\Config\storage\PhpFileStorage;
use Besnovatyj\Config\storage\StorageInterface;


return function (\yii\di\Container $container): void {
    // Storage Layer
    // Используем PhpFileStorage по умолчанию (хранение в PHP файле)
    // Для переключения на БД замените PhpFileStorage на DatabaseStorage
    $container->setSingleton(StorageInterface::class, fn() => new PhpFileStorage(
        filePath: '@var/config/configModuleParams.php', // Путь к файлу хранилища
    ));

    // Repository Layer
    $container->setSingleton(ConfigRepository::class, fn() => new ConfigRepository(
        storage: $container->get(StorageInterface::class),
    ));

    // Service Layer
    $container->setSingleton(ConfigCollector::class, ConfigCollector::class);

    $container->setSingleton(ConfigApplier::class, ConfigApplier::class);

    $container->setSingleton(ConfigService::class, fn() => new ConfigService(
        collector: $container->get(ConfigCollector::class),
        repository: $container->get(ConfigRepository::class),
        applier: $container->get(ConfigApplier::class),
        // cache получается лениво через Yii::$app->cache
    ));
};

