<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Config\storage;

use Yii;
use yii\base\Exception;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;

/**
 * StoragePhp represents the configuration storage based on local PHP files.
 */
class StoragePhp extends Storage
{
    /**
     * @var string name of the file, which should be used to store values.
     */
    public string $fileName = '@runtime/app_config_params.php';

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function save(array $values): bool
    {
        $this->clear();
        $fileName = Yii::getAlias($this->fileName);
        FileHelper::createDirectory(dirname($fileName));
        $bytesWritten = file_put_contents($fileName, $this->composeFileContent($values));
        $this->invalidateScriptCache($fileName);
        return ($bytesWritten > 0);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        $fileName = Yii::getAlias($this->fileName);
        if (file_exists($fileName)) {
            return require($fileName);
        } else {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $fileName = Yii::getAlias($this->fileName);
        if (file_exists($fileName)) {
            $this->invalidateScriptCache($fileName);
            return unlink($fileName);
        }
        return true;
    }

    /**
     * Composes file content for the given values.
     * @param array $values values to be saved.
     * @return string file content.
     */
    protected function composeFileContent(array $values): string
    {
        return "<?php\n\nreturn " . VarDumper::export($values) . ';';
    }

    /**
     * Invalidates precompiled script cache (such as OPCache or APC) for the given file.
     * @param string $fileName file name.
     */
    protected function invalidateScriptCache(string $fileName): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fileName, true);
        }
        if (function_exists('apc_delete_file')) {
            @apc_delete_file($fileName);
        }
    }
}
