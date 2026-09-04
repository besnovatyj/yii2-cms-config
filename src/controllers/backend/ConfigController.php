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
     * Отображает и обрабатывает форму редактирования конфигурации.
     *
     * Все параметры всегда отдаются в представление целиком: разделы переключаются
     * на клиенте, поэтому одна отправка формы сохраняет любые изменения.
     * Параметр $category задаёт лишь раздел, открытый при загрузке страницы
     * (сохраняет работоспособность внешних ссылок вида ?category=Modman).
     *
     * @param string $category Открываемый раздел: его ключ (app, Blog, User) либо ID модуля
     * @return Response|string
     */
    public function actionIndex(string $category = ''): Response|string
    {
        try {
            // Получаем все элементы конфигурации с текущими значениями
            $items = $this->configService->getAllWithValues();

            // Обработка POST запроса (сохранение)
            if (Yii::$app->request->isPost) {
                $values = Yii::$app->request->post('ConfigItem', []);
                $result = $this->configService->saveValues($values);

                if ($result['success']) {
                    Yii::$app->session->setFlash('success', 'Настройки сохранены.');
                    return $this->redirect(['index', 'category' => $category]);
                }

                Yii::$app->session->setFlash('error', 'Значения не сохранены: есть ошибки валидации.');

                // Возвращаем в форму то, что ввёл пользователь, а не сохранённые значения
                $groups = $this->configService->groupWithValues($this->mergeSubmitted($items, $values));

                return $this->render('index', [
                    'groups' => $groups,
                    'errors' => $result['errors'],
                    'activeGroup' => $this->configService->resolveGroupKey($groups, $category),
                ]);
            }

            // Отображаем форму
            $groups = $this->configService->groupWithValues($items);

            return $this->render('index', [
                'groups' => $groups,
                'errors' => [],
                'activeGroup' => $this->configService->resolveGroupKey($groups, $category),
            ]);
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
            return $this->render('index', [
                'groups' => [],
                'errors' => [],
                'activeGroup' => '',
            ]);
        }
    }

    /**
     * Подставляет отправленные значения поверх текущих (для перерисовки формы с ошибками).
     *
     * @param array $items Результат ConfigService::getAllWithValues()
     * @param array $values Отправленные значения [id => value]
     * @return array
     */
    private function mergeSubmitted(array $items, array $values): array
    {
        foreach ($values as $id => $value) {
            if (isset($items[$id])) {
                $items[$id]['value'] = $value;
            }
        }

        return $items;
    }

    /**
     * Восстанавливает значения по умолчанию.
     * Очищает сохраненные значения — все либо только одного раздела.
     *
     * @return Response
     */
    public function actionRestoreDefaults(): Response
    {
        $category = (string)Yii::$app->request->post('category', '');

        try {
            $this->configService->restoreDefaults($category);
            Yii::$app->session->setFlash(
                'success',
                $category === ''
                    ? 'Значения по умолчанию восстановлены.'
                    : "Значения по умолчанию восстановлены для раздела «{$category}»."
            );
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }

        return $this->redirect(['index', 'category' => $category]);
    }
}
