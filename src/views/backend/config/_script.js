/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 *
 * Страница параметров: переключение разделов, поиск по всем параметрам,
 * фильтр «только изменённые» и защита от ухода со страницы с несохранёнными правками.
 *
 * Все поля всегда присутствуют в DOM, разделы лишь скрываются: одна отправка формы
 * сохраняет правки любого числа разделов. Без JS страница показывает все разделы сразу.
 */
(function () {
    'use strict';

    var root = document.getElementById('config-index');
    if (!root) {
        return;
    }

    var form = document.getElementById('config-form');
    var nav = document.getElementById('cfg-nav');
    var search = document.getElementById('cfg-search');
    var searchClear = document.getElementById('cfg-search-clear');
    var onlyChanged = document.getElementById('cfg-only-changed');
    var emptyNotice = document.getElementById('cfg-empty');
    var dirtyHint = document.getElementById('cfg-dirty-hint');
    var sections = Array.prototype.slice.call(root.querySelectorAll('.cfg-section'));

    var activeGroup = root.getAttribute('data-active-group') || '';
    var submitting = false;

    /** Показывает/скрывает разделы и поля по текущему разделу и фильтрам. */
    function apply() {
        var query = (search.value || '').trim().toLowerCase();
        var changedOnly = onlyChanged.checked;
        var filtering = query !== '' || changedOnly;
        var visibleFields = 0;

        sections.forEach(function (section) {
            var inScope = activeGroup === '' || section.getAttribute('data-group') === activeGroup;
            var shown = 0;

            Array.prototype.forEach.call(section.querySelectorAll('.cfg-field'), function (field) {
                var matches = inScope
                    && (query === '' || field.getAttribute('data-search').indexOf(query) !== -1)
                    && (!changedOnly || field.getAttribute('data-changed') === '1'
                        || field.querySelector('.cfg-field-dirty') !== null);

                field.classList.toggle('cfg-hidden', !matches);
                if (matches) {
                    shown++;
                }
            });

            // Подзаголовки подгрупп при фильтрации теряют смысл: часть их полей скрыта
            Array.prototype.forEach.call(section.querySelectorAll('.cfg-subheading'), function (heading) {
                heading.classList.toggle('cfg-hidden', filtering);
            });

            section.classList.toggle('cfg-hidden', shown === 0);
            visibleFields += shown;
        });

        emptyNotice.classList.toggle('d-none', visibleFields > 0 || !filtering);
        searchClear.classList.toggle('d-none', query === '');
    }

    /** Переключает раздел, отражая выбор в подсветке меню и в адресе страницы. */
    function selectGroup(group) {
        activeGroup = group;

        Array.prototype.forEach.call(nav.querySelectorAll('.nav-link'), function (link) {
            link.classList.toggle('active', (link.getAttribute('data-group') || '') === group);
        });

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (group === '') {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', group);
            }
            window.history.replaceState({}, '', url.toString());
        }

        apply();
    }

    nav.addEventListener('click', function (event) {
        var link = event.target.closest('.nav-link');
        if (link) {
            selectGroup(link.getAttribute('data-group') || '');
        }
    });

    // Поиск идёт по всем разделам сразу — иначе пришлось бы помнить, какой модуль
    // объявил параметр; поэтому ввод запроса переводит страницу в раздел «Все».
    search.addEventListener('input', function () {
        if (search.value.trim() !== '' && activeGroup !== '') {
            selectGroup('');
            return;
        }
        apply();
    });

    searchClear.addEventListener('click', function () {
        search.value = '';
        search.focus();
        apply();
    });

    onlyChanged.addEventListener('change', apply);

    // Отметка несохранённых правок
    if (form) {
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () {
            submitting = true;
        });
    }

    function markDirty(event) {
        var field = event.target.closest('.cfg-field');
        if (!field) {
            return;
        }

        var inner = field.querySelector('.cfg-field-inner');
        if (inner) {
            inner.classList.add('cfg-field-dirty');
        }

        var dirty = root.querySelectorAll('.cfg-field-dirty').length;
        dirtyHint.textContent = dirty > 0 ? 'Несохранённых правок: ' + dirty : '';
    }

    window.addEventListener('beforeunload', function (event) {
        if (!submitting && root.querySelector('.cfg-field-dirty')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    apply();
})();
