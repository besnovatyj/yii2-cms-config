<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Config\controllers\backend;

use Besnovatyj\Kernel\controller\ControllerTrait;
use Besnovatyj\Config\services\ConfigService;
use Yii;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\Response;
use Exception;

/**
 * ConfigController - тонкий контроллер для управления конфигурацией приложения
 * Вся бизнес-логика вынесена в ConfigService
 */
class ConfigController extends Controller
{
    use ControllerTrait;

    /**
     * @param string $id
     * @param mixed $module
     * @param ConfigService $configService Сервис инжектируется через DI
     * @param array $config
     */
    public function __construct(
        $id,
        $module,
        private readonly ConfigService $configService,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Отображает и обрабатывает форму редактирования конфигурации
     *
     * @param string $category Фильтр по категории (app, blog, user и т.д.)
     * @return Response|string
     */
    public function actionIndex(string $category = ''): Response|string
    {
        try {
            // Получаем все элементы конфигурации с текущими значениями
            $items = $this->configService->getAllWithValues();

            // Фильтруем по категории если указана
            if (!empty($category)) {
                $items = array_filter($items, function ($data) use ($category) {
                    return $data['item']->category === $category;
                });
            }

            // Обработка POST запроса (сохранение)
            if (Yii::$app->request->isPost) {
                $values = Yii::$app->request->post('ConfigItem', []);
                $result = $this->configService->saveValues($values);

                if ($result['success']) {
                    Yii::$app->session->setFlash('success', 'Configuration saved successfully.');
                    return $this->redirect(['index', 'category' => $category]);
                } else {
                    Yii::$app->session->setFlash('error', 'Validation errors occurred.');
                    // Передаем ошибки в view
                    return $this->render('index', [
                        'items' => $items,
                        'errors' => $result['errors'],
                        'category' => $category,
                    ]);
                }
            }

            // Отображаем форму
            return $this->render('index', [
                'items' => $items,
                'errors' => [],
                'category' => $category,
            ]);
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
            return $this->render('index', [
                'items' => [],
                'errors' => [],
                'category' => '',
            ]);
        }
    }

    /**
     * Восстанавливает значения по умолчанию.
     * Очищает все сохраненные значения.
     *
     * @return Response
     */
    public function actionRestoreDefaults(): Response
    {
        try {
            $this->configService->restoreDefaults();
            Yii::$app->session->setFlash('success', 'Default values restored successfully.');
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }

        return $this->redirect(['index']);
    }
}
