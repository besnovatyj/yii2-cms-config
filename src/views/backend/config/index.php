<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Config\entities\ConfigItem;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * @var array $items Массив [id => ['item' => ConfigItem, 'value' => mixed]]
 * @var array $errors Ошибки валидации [id => [errors]]
 * @var string $category Текущая категория фильтра
 */

$this->title = 'Параметры приложения и модулей';
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="config-index">
    <p class="d-flex gap-2">
        <?= Html::a('All', ['/Config/backend/config/index'], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('App only', ['/Config/backend/config/index', 'category' => 'app'], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Restore defaults', ['restore-defaults'],
            [
                'class' => 'btn btn-danger ms-auto', // ms-auto = margin-start: auto
                'data-confirm' => 'Are you sure you want to restore default values? This will clear all custom settings.',
                'data-method' => 'post',
            ]
        ); ?>
    </p>

    <?php $form = ActiveForm::begin(['id' => 'config-form']); ?>

    <div class="card">
        <div class="card-header"></div>

        <div class="card-body">
            <?php if (!empty($items)): ?>
                <?php
                // Группируем по категориям для лучшей организации
                $groupedItems = [];
                foreach ($items as $id => $data) {
                    $category = $data['item']->category;
                    if (!isset($groupedItems[$category])) {
                        $groupedItems[$category] = [];
                    }
                    $groupedItems[$category][$id] = $data;
                }
                ?>

                <?php foreach ($groupedItems as $categoryName => $categoryItems): ?>
                    <h5 class="mt-3 mb-2 text-muted"><?= Html::encode(ucfirst($categoryName)) ?></h5>

                    <?php foreach ($categoryItems as $id => $data): ?>
                        <?php
                        /** @var ConfigItem $item */
                        $item = $data['item'];
                        $value = $data['value'];
                        $inputOptions = $item->inputOptions;
                        $inputType = ArrayHelper::getValue($inputOptions, 'type', 'input');
                        ?>

                        <div class="form-group pb-3">
                            <label for="config-<?= Html::encode($id) ?>">
                                <?= Html::encode($item->label) ?>
                            </label>

                            <?php
                            $fieldOptions = [
                                'id' => 'config-' . $id,
                                'name' => "ConfigItem[{$id}]",
                                'value' => $value,
                            ];

                            switch ($inputType) {
                                case 'checkbox':
                                    echo Html::checkbox($fieldOptions['name'], (bool)$value, ['id' => $fieldOptions['id'], 'class' => 'form-check-input']);
                                    break;

                                case 'textarea':
                                    echo Html::textarea($fieldOptions['name'], $value, array_merge($fieldOptions, ['class' => 'form-control', 'rows' => 3]));
                                    break;

                                case 'dropdown':
                                    $dropdownItems = ArrayHelper::getValue($inputOptions, 'items', []);
                                    echo Html::dropDownList($fieldOptions['name'], $value, $dropdownItems, array_merge($fieldOptions, ['class' => 'form-control']));
                                    break;

                                case 'input':
                                default:
                                    echo Html::textInput($fieldOptions['name'], $value, array_merge($fieldOptions, ['class' => 'form-control', 'maxlength' => true]));
                                    break;
                            }
                            ?>

                            <?php if (!empty($item->description)): ?>
                                <small class="text-muted"><?= $item->description ?></small>
                            <?php endif; ?>

                            <?php if (isset($errors[$id]) && !empty($errors[$id])): ?>
                                <div class="invalid-feedback d-block">
                                    <?php foreach ($errors[$id] as $error): ?>
                                        <div><?= Html::encode($error) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div style="padding-bottom: 1rem;"></div>

                <?php endforeach; ?>

            <?php else: ?>
                <p class="text-muted">No configuration elements found.</p>
            <?php endif; ?>
        </div>

        <div class="card-footer">
            <div class="d-grid gap-2">
                <?= Html::submitButton('Save Configuration', ['class' => 'btn btn-success', 'role' => 'button']) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>
