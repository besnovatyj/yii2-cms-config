<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\storage;

use Yii;
use yii\helpers\VarDumper;

/**
 * PhpFileStorage - хранение конфигурации в PHP файле
 * Быстро, не требует БД, удобно для small/medium проектов
 *
 * Формат файла:
 * ```php
 * return [
 *     'param_id' => 'value',
 *     'another_param' => 'another_value',
 * ];
 * ```
 */
class PhpFileStorage implements StorageInterface
{
    private array $data = [];
    private bool $dataLoaded = false;

    /**
     * @param string $filePath Путь к файлу хранилища (может содержать alias, например '@var/config/params.php')
     */
    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): ?string
    {
        $this->loadData();
        return $this->data[$id] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        $this->loadData();
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function save(array $values): bool
    {
        $filePath = Yii::getAlias($this->filePath);

        // Убеждаемся что директория существует
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                Yii::error("Failed to create directory: {$dir}", __METHOD__);
                return false;
            }
        }

        // Формируем содержимое файла // TODO - где-то там есть Besnovatyj\Helpers\ArrayExportHelper
        $content = "<?php\n\nreturn " . VarDumper::export($values) . ";\n";

        // Записываем в файл
        $result = file_put_contents($filePath, $content, LOCK_EX);

        if ($result === false) {
            Yii::error("Failed to write config file: {$filePath}", __METHOD__);
            return false;
        }

        // Обновляем кэш
        $this->data = $values;
        $this->dataLoaded = true;

        // Очищаем opcode cache для этого файла
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $filePath = Yii::getAlias($this->filePath);

        if (file_exists($filePath)) {
            $result = unlink($filePath);
            if (!$result) {
                Yii::error("Failed to delete config file: {$filePath}", __METHOD__);
                return false;
            }
        }

        $this->data = [];
        $this->dataLoaded = false;

        return true;
    }

    /**
     * Загружает данные из файла
     */
    private function loadData(): void
    {
        if ($this->dataLoaded) {
            return;
        }

        $filePath = Yii::getAlias($this->filePath);

        if (file_exists($filePath)) {
            try {
                $data = require $filePath;
                if (is_array($data)) {
                    $this->data = $data;
                } else {
                    Yii::warning("Config file '{$filePath}' did not return an array", __METHOD__);
                    $this->data = [];
                }
            } catch (\Exception $e) {
                Yii::error("Failed to load config file '{$filePath}': {$e->getMessage()}", __METHOD__);
                $this->data = [];
            }
        } else {
            $this->data = [];
        }

        $this->dataLoaded = true;
    }
}
