<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Config\entities\ConfigItem;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var array $groups Разделы: [key => ['key','label','total','overridden','items' => [id => ['item','value','overridden']]]]
 * @var array $errors Ошибки валидации [id => [errors]]
 * @var string $activeGroup Раздел, открытый при загрузке ('' — показать все)
 */

$this->title = 'Параметры приложения и модулей';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss(file_get_contents(__DIR__ . '/_style.css'));
$this->registerJs(file_get_contents(__DIR__ . '/_script.js'), View::POS_END);

$totalCount = array_sum(array_column($groups, 'total'));
$totalOverridden = array_sum(array_column($groups, 'overridden'));


/**
 * Метка параметра без служебного префикса вида «[Модуль] ».
 * В плоском списке префикс был единственным указанием на владельца опции,
 * внутри карточки модуля он только мешает.
 */
$shortLabel = static function (string $label): string {
    $short = trim((string)preg_replace('/^\s*\[[^]]{1,40}]\s*/u', '', $label));

    return $short !== '' ? $short : $label;
};

/** Описание-«акцессор» (Yii::$app->getModule(...)->params[...]) показываем как техническую подсказку, а не как текст. */
$isAccessorHint = static fn(string $text): bool => str_contains($text, '::$app') || str_starts_with(trim($text), '$');

/** Значение для поля ввода: массивы/объекты в форму не помещаются. */
$scalar = static fn(mixed $value): string => is_scalar($value) ? (string)$value : '';

/**
 * Упорядочивает параметры раздела по необязательной подгруппе ('group' в options.php),
 * сохраняя исходный порядок внутри подгруппы. Параметры без подгруппы идут первыми.
 */
$groupItems = static function (array $items): array {
    $buckets = [];
    foreach ($items as $id => $data) {
        $buckets[$data['item']->group][$id] = $data;
    }
    ksort($buckets);

    return $buckets === [] ? [] : array_replace(...array_values($buckets));
};

?>

<?php if ($groups === []): ?>
    <div class="alert alert-secondary mb-0">
        <i class="bi bi-info-circle me-1"></i>Настраиваемых параметров не найдено.
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="config-index" id="config-index" data-active-group="<?= Html::encode($activeGroup) ?>">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="input-group input-group-sm cfg-search">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <?= Html::input('search', null, null, [
                'id' => 'cfg-search',
                'class' => 'form-control',
                'placeholder' => 'Поиск по названию, ключу или пути…',
                'autocomplete' => 'off',
            ]) ?>
            <button type="button" class="btn btn-outline-secondary d-none" id="cfg-search-clear" title="Очистить">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="form-check form-switch mb-0 ms-1">
            <input class="form-check-input" type="checkbox" id="cfg-only-changed">
            <label class="form-check-label small text-body-secondary" for="cfg-only-changed">
                Только изменённые<?= $totalOverridden > 0 ? ' (' . $totalOverridden . ')' : '' ?>
            </label>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-body-secondary small">
                Параметров: <?= $totalCount ?> в <?= count($groups) ?> разделах
            </span>
            <?= Html::a('<i class="bi bi-arrow-counterclockwise me-1"></i>Сбросить всё', ['restore-defaults'], [
                'class' => 'btn btn-sm btn-outline-danger',
                'data-confirm' => 'Сбросить сохранённые значения ВСЕХ разделов к значениям по умолчанию?',
                'data-method' => 'post',
            ]) ?>
        </div>
    </div>

    <ul class="nav nav-pills cfg-nav gap-1 mb-3" id="cfg-nav">
        <li class="nav-item">
            <button type="button" class="nav-link py-1 px-3<?= $activeGroup === '' ? ' active' : '' ?>" data-group="">
                Все
                <span class="badge rounded-pill text-bg-light ms-1"><?= $totalCount ?></span>
            </button>
        </li>
        <?php foreach ($groups as $key => $group): ?>
            <li class="nav-item">
                <button type="button" class="nav-link py-1 px-3<?= $activeGroup === $key ? ' active' : '' ?>"
                        data-group="<?= Html::encode($key) ?>">
                    <?= Html::encode($group['label']) ?>
                    <span class="badge rounded-pill text-bg-light ms-1"><?= $group['total'] ?></span>
                    <?php if ($group['overridden'] > 0): ?>
                        <span class="cfg-dot" title="Изменённых параметров: <?= $group['overridden'] ?>"></span>
                    <?php endif; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php $form = ActiveForm::begin(['id' => 'config-form']); ?>

    <div id="cfg-sections">
        <?php foreach ($groups as $key => $group): ?>
            <section class="card mb-3 cfg-section" data-group="<?= Html::encode($key) ?>"
                     id="cfg-section-<?= Html::encode($key) ?>">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-sliders text-body-secondary"></i>
                    <span class="fw-semibold"><?= Html::encode($group['label']) ?></span>
                    <span class="badge rounded-pill text-bg-light"><?= $group['total'] ?></span>
                    <?php if ($group['overridden'] > 0): ?>
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">
                            изменено: <?= $group['overridden'] ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($group['overridden'] > 0): ?>
                        <?= Html::a('<i class="bi bi-arrow-counterclockwise"></i>', ['restore-defaults'], [
                            'class' => 'btn btn-sm btn-link link-secondary ms-auto p-0',
                            'title' => 'Сбросить раздел к значениям по умолчанию',
                            'data-confirm' => "Сбросить значения раздела «{$group['label']}» к значениям по умолчанию?",
                            'data-method' => 'post',
                            'data-params' => json_encode(['category' => $key], JSON_THROW_ON_ERROR),
                            // ссылка находится внутри формы параметров: без data-form
                            // yii.js отправил бы на сброс саму форму параметров
                            'data-form' => 'cfg-reset-form',
                        ]) ?>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <?php $subGroup = null; ?>
                        <?php foreach ($groupItems($group['items']) as $id => $data): ?>
                            <?php
                            /** @var ConfigItem $item */
                            $item = $data['item'];

                            // Подзаголовок появляется, только если модуль сам разбил опции ключом 'group'
                            if ($item->group !== $subGroup) {
                                $subGroup = $item->group;
                                if ($subGroup !== '') {
                                    echo Html::tag(
                                        'div',
                                        Html::tag('span', Html::encode($subGroup), ['class' => 'text-body-secondary text-uppercase small fw-semibold']),
                                        ['class' => 'col-12 pb-0 cfg-subheading']
                                    );
                                }
                            }

                            $value = $data['value'];
                            $inputType = ArrayHelper::getValue($item->inputOptions, 'type', 'input');
                            $hasError = !empty($errors[$id]);
                            $inputId = 'config-' . $id;
                            $inputName = "ConfigItem[{$id}]";
                            $description = trim($item->description);
                            $isAccessor = $description !== '' && $isAccessorHint($description);
                            $wide = in_array($inputType, ['textarea', 'checkbox'], true);
                            // Строка, по которой работает поиск на клиенте
                            $haystack = mb_strtolower($item->label . ' ' . $id . ' ' . $item->path . ' ' . $description);
                            ?>
                            <div class="<?= $wide ? 'col-12' : 'col-12 col-xl-6' ?> cfg-field"
                                 data-search="<?= Html::encode($haystack) ?>"
                                 data-changed="<?= $data['overridden'] ? '1' : '0' ?>">
                                <div class="cfg-field-inner h-100<?= $data['overridden'] ? ' cfg-field-changed' : '' ?><?= $hasError ? ' cfg-field-error' : '' ?>">
                                    <label class="form-label mb-1 d-flex align-items-baseline gap-2"
                                           for="<?= Html::encode($inputId) ?>"
                                           title="<?= Html::encode($item->label) ?>">
                                        <span class="fw-semibold"><?= Html::encode($shortLabel($item->label)) ?></span>
                                        <?php if ($data['overridden']): ?>
                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis cfg-badge">изменено</span>
                                        <?php endif; ?>
                                    </label>

                                    <?php
                                    $controlOptions = [
                                        'id' => $inputId,
                                        'class' => 'form-control' . ($hasError ? ' is-invalid' : ''),
                                    ];

                                    switch ($inputType) {
                                        case 'checkbox':
                                            echo Html::tag('div', Html::checkbox($inputName, (bool)$value, [
                                                'id' => $inputId,
                                                'class' => 'form-check-input' . ($hasError ? ' is-invalid' : ''),
                                                // без uncheck снятая галочка просто не отправлялась бы
                                                'uncheck' => '0',
                                                'value' => '1',
                                            ]), ['class' => 'form-check form-switch']);
                                            break;

                                        case 'textarea':
                                            echo Html::textarea($inputName, $scalar($value), $controlOptions + ['rows' => 3]);
                                            break;

                                        case 'dropdown':
                                            // Варианты берём у самого элемента: он знает, перечислены ли они
                                            // в options.php или их собирает поставщик (OptionItemsProvider).
                                            echo Html::dropDownList(
                                                $inputName,
                                                $scalar($value),
                                                $item->inputItems(),
                                                ['id' => $inputId, 'class' => 'form-select' . ($hasError ? ' is-invalid' : '')]
                                            );
                                            break;

                                        case 'input':
                                        default:
                                            echo Html::textInput($inputName, $scalar($value), $controlOptions + ['maxlength' => true]);
                                            break;
                                    }
                                    ?>

                                    <?php if ($hasError): ?>
                                        <div class="invalid-feedback d-block">
                                            <?php foreach ($errors[$id] as $error): ?>
                                                <div><?= Html::encode($error) ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($description !== '' && !$isAccessor): ?>
                                        <div class="form-text mt-1"><?= $description ?></div>
                                    <?php endif; ?>

                                    <div class="cfg-meta mt-1">
                                        <code title="Идентификатор параметра"><?= Html::encode($id) ?></code>
                                        <?php if ($item->path !== ''): ?>
                                            <span class="cfg-meta-sep">·</span>
                                            <code title="Путь в конфигурации"><?= Html::encode($item->path) ?></code>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-secondary d-none" id="cfg-empty">
        <i class="bi bi-search me-1"></i>По заданным условиям параметров не найдено.
    </div>

    <div class="cfg-actions d-flex align-items-center gap-3 py-2">
        <?= Html::submitButton('<i class="bi bi-check2 me-1"></i>Сохранить', ['class' => 'btn btn-success']) ?>
        <span class="text-body-secondary small" id="cfg-dirty-hint"></span>
    </div>

    <?php ActiveForm::end(); ?>

    <?php // Пустая форма-носитель для кнопок сброса раздела (data-form), с CSRF-токеном ?>
    <?= Html::beginForm(['restore-defaults'], 'post', ['id' => 'cfg-reset-form', 'class' => 'd-none']) ?>
    <?= Html::endForm() ?>

</div>
