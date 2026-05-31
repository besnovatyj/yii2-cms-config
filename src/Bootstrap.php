<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config;

use Besnovatyj\Config\repositories\ConfigRepository;
use Besnovatyj\Config\services\ConfigApplier;
use Besnovatyj\Config\services\ConfigCollector;
use Besnovatyj\Config\services\ConfigService;
use Besnovatyj\Config\storage\PhpFileStorage;
use Besnovatyj\Config\storage\StorageInterface;
use Yii;
use yii\base\BootstrapInterface;
use yii\di\Instance;

/**
 * Bootstrap - применяет сохраненную конфигурацию к приложению при запуске
 *
 * Этот класс регистрируется в bootstrap секции приложения и автоматически
 * вызывается при инициализации Yii.
 *
 * ПРОСТАЯ И ПОНЯТНАЯ ЛОГИКА:
 * 1. Регистрирует DI зависимости (т.к. модуль еще не инициализирован)
 * 2. Получает ConfigService из DI контейнера
 * 3. Вызывает applyConfiguration()
 * 4. ConfigService использует кэш для производительности
 * 5. ConfigApplier применяет значения БЕЗ рекурсии
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        try {
            // Регистрируем DI контейнер (модуль еще не инициализирован на этом этапе)
            $this->registerDependencies();

            /** @var ConfigService $configService */
            $configService = Yii::$container->get(ConfigService::class);
            $configService->applyConfiguration();
        } catch (\Exception $e) {
            // Ошибки при первом запуске (до миграций БД) игнорируем
            Yii::warning("Failed to apply configuration: {$e->getMessage()}", __METHOD__);
        }
    }

    /**
     * Регистрирует зависимости в DI контейнере
     * Выполняется вручную, так как модуль еще не инициализирован при bootstrap
     */
    private function registerDependencies(): void
    {
        $container = Yii::$container;

        // Регистрируем только если еще не зарегистрировано
        if (!$container->has(StorageInterface::class)) {
            // Storage Layer
            $container->setSingleton(StorageInterface::class, [
                'class' => PhpFileStorage::class,
                '__construct()' => [
                    Yii::getAlias('@var/config/configModuleParams.php'),
                ],
            ]);

            // Repository Layer
            $container->setSingleton(ConfigRepository::class, [
                'class' => ConfigRepository::class,
                '__construct()' => [
                    'storage' => Instance::of(StorageInterface::class),
                ],
            ]);

            // Service Layer
            $container->setSingleton(ConfigCollector::class, ConfigCollector::class);
            $container->setSingleton(ConfigApplier::class, ConfigApplier::class);

            $container->setSingleton(ConfigService::class, [
                'class' => ConfigService::class,
                '__construct()' => [
                    'collector' => Instance::of(ConfigCollector::class),
                    'repository' => Instance::of(ConfigRepository::class),
                    'applier' => Instance::of(ConfigApplier::class),
                    // cache получается лениво через Yii::$app->cache внутри ConfigService
                ],
            ]);
        }
    }
}
