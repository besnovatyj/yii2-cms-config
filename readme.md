# Config Module

Модуль динамического управления конфигурацией приложения Yii2 с чистой архитектурой.

## Возможности

- ✅ Динамическое изменение параметров приложения через UI
- ✅ Автоматический сбор настроек из всех модулей
- ✅ Гибкое хранилище (PHP файл или БД)
- ✅ Кэширование для производительности
- ✅ Валидация значений перед сохранением
- ✅ Dependency Injection через контейнер

## Архитектура

```
┌─────────────────────────────────────────────────┐
│              Bootstrap                          │
│  (Применяет конфигурацию при запуске)           │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│           ConfigService                         │
│  (Оркестратор всей бизнес-логики)               │
└──────┬──────────┬──────────┬────────────────────┘
       │          │          │
       ▼          ▼          ▼
┌───────────┐ ┌──────────┐ ┌──────────────┐
│ Collector │ │Repository│ │   Applier    │
│(Сбор      │ │(Хранение)│ │(Применение)  │
│ опций)    │ │          │ │              │
└───────────┘ └────┬─────┘ └──────────────┘
                   │
                   ▼
            ┌────────────┐
            │  Storage   │
            │ (PHP/БД)   │
            └────────────┘
```

### Слои

#### Entity Layer
- **ConfigItem** - иммутабельный value object для элемента конфигурации
- **ConfigValue** - ActiveRecord для хранения в БД

#### Storage Layer
- **StorageInterface** - интерфейс хранилища
- **PhpFileStorage** - хранение в PHP файле (по умолчанию)
- **DatabaseStorage** - хранение в базе данных

#### Repository Layer
- **ConfigRepository** - абстракция над хранилищем

#### Service Layer
- **ConfigCollector** - собирает опции из `config/options.php` всех модулей
- **ConfigApplier** - применяет значения к приложению (без рекурсии!)
- **ConfigService** - главный сервис-оркестратор

#### Controller Layer
- **ConfigController** - тонкий контроллер с DI

## Интерфейс администратора

Страница `/Config/backend/config/index` показывает параметры не плоским списком, а разделами:

- **мини-меню разделов** сверху — по одному пункту на модуль/категорию, со счётчиком параметров
  и точкой у разделов с изменёнными значениями;
- **карточка на раздел** — переключение разделов происходит на клиенте, все поля остаются в форме,
  поэтому одна кнопка «Сохранить» сохраняет правки любого числа разделов;
- **поиск** по названию, идентификатору, пути и описанию — по всем разделам сразу
  (не нужно помнить, какой модуль объявил параметр);
- фильтр **«только изменённые»** и подсветка параметров, значение которых переопределено
  (то есть отличается от дефолта из конфигурации модуля);
- **сброс раздела** к значениям по умолчанию — иконка в шапке карточки; сброс всех разделов — кнопка сверху.

Ссылка вида `/Config/backend/config/index?category=Modman` открывает страницу
сразу на нужном разделе (используется другими модулями).

### Как параметр попадает в раздел

Ключ раздела определяет `ConfigItem::groupKey()`:

1. `category` из `options.php`, если задана;
2. иначе ID модуля, объявившего опцию (заполняется `ConfigCollector`);
3. иначе `app`.

Раздел `app` («Приложение») всегда идёт первым, остальные — по алфавиту.
Необязательный ключ `group` разбивает параметры одного раздела на подгруппы с подзаголовками.

## Использование

### Добавление настроек в модуль

Создайте файл `config/options.php` в вашем модуле:

```php
<?php
return [
    'my_module_param' => [
        'path' => 'modules.mymodule.params.my_param',
        'label' => 'My Parameter',
        'description' => 'Description of parameter',
        'category' => 'mymodule',  // Раздел в UI; если не задан — берётся ID модуля
        'group' => '',             // Необязательная подгруппа внутри раздела
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',  // input, checkbox, textarea, dropdown
        ],
    ],
];
```

### Доступ к параметрам в коде

```php
// Получить значение параметра модуля
$value = Yii::$app->getModule('mymodule')->params['my_param'];
```

### Параметры для настройки
Параметры могут быть многоуровневыми массивами с любым уровнем вложенности.

```php
// Пример как задаются 
return [
    'my_module_param' => [
        'path' => 'modules.mymodule.params.category.my_param',
        'label' => 'My Parameter',
        'description' => 'Description of parameter',
        'category' => 'mymodule',  // Для группировки в UI
        'rules' => [
            ['required'],
            ['string'],
        ],
        'inputOptions' => [
            'type' => 'input',  // input, checkbox, textarea, dropdown
        ],
    ],
];
// Получить значение параметра модуля
$value = Yii::$app->getModule('mymodule')->params['category']['my_param'];
```


### Программное сохранение значений

```php
/** @var ConfigService $configService */
$configService = Yii::$container->get(ConfigService::class);

$result = $configService->saveValues([
    'my_module_param' => 'new value',
]);

if ($result['success']) {
    echo "Saved!";
}
```

Переданные значения дописываются к уже сохранённым: сохранение части параметров
не стирает остальные. Неизвестные идентификаторы игнорируются.

### Сброс к значениям по умолчанию

```php
$configService->restoreDefaults();          // все разделы
$configService->restoreDefaults('Modman');  // только один раздел
```

## Переключение на БД хранилище

### 1. Измените `config/container.php`:

```php
use Besnovatyj\Config\storage\DatabaseStorage;  // вместо PhpFileStorage

return [
    'singletons' => [
        StorageInterface::class => [
            'class' => DatabaseStorage::class,  // Изменить
            // ...
        ],
    ],
];
```

### 2. Измените `Bootstrap.php`:

В методе `registerDependencies()` замените `PhpFileStorage` на `DatabaseStorage`.

### 3. Примените миграции:

```bash
./yii migrate/up --migrationPath=@besnovatyj/Config/migrations
```

## Типы полей ввода

### Input
```php
'inputOptions' => ['type' => 'input']
```

### Checkbox
```php
'inputOptions' => ['type' => 'checkbox']
```

### Textarea
```php
'inputOptions' => ['type' => 'textarea']
```

### Dropdown
```php
'inputOptions' => [
    'type' => 'dropdown',
    'items' => [
        'value1' => 'Label 1',
        'value2' => 'Label 2',
    ],
]
```

### Dropdown со списком, который собирает система (`itemsProvider`)

Перечисленные вручную `items` годятся, пока список — это набор режимов («обрезать / вписывать»,
«да / нет»): он и правда постоянный.

Другое дело — список, который равен **составу системы**: установленные ядра поиска, движки
редактора, темы. Записанный литералом, он неизбежно расходится с правдой: поставили ещё один
пакет — надо не забыть дописать вариант; выключили модуль в менеджере модулей — вариант всё равно
предлагается администратору. Кроме того, такой список приходится держать в двух местах сразу —
в `items` и в правиле `['in', 'range' => ...]`.

Собирать список прямо в `options.php` нельзя: менеджер модулей считает от объявленных опций
контрольную сумму манифеста, и модуль с «плавающим» списком помечался бы как изменившийся при
каждой установке соседнего пакета. Поэтому объявление остаётся статичным, а вместо готового
массива указывается класс-поставщик:

```php
'search_engine' => [
    'path'         => 'modules.Search.params.engine',
    'label'        => '[Search] Активное ядро поиска',
    'category'     => 'Search',
    'rules'        => [['required']],
    'inputOptions' => [
        'type'          => 'dropdown',
        'itemsProvider' => \Besnovatyj\Search\settings\EngineOptionItems::class,
    ],
],
```

Класс реализует контракт `Besnovatyj\Contracts\config\OptionItemsProvider`:

```php
final class EngineOptionItems implements OptionItemsProvider
{
    public function __construct(private readonly EngineRegistry $engines)
    {
    }

    /** @return array<string, string> значение => подпись */
    public function items(): array
    {
        $items = [];
        foreach ($this->engines->descriptors() as $descriptor) {
            $items[$descriptor->key] = $descriptor->label;
        }

        return $items;
    }
}
```

Что происходит дальше:

- поставщик резолвится **DI-контейнером** в момент отрисовки страницы настроек, то есть когда
  приложение уже поднято и знает, что установлено на самом деле; поэтому он может свободно
  зависеть от сервисов своего модуля через конструктор;
- список одновременно становится **правилом валидации**: сохранить значение вне набора ключей
  нельзя, писать `['in', 'range' => ...]` не нужно (и не следует — это был бы второй список,
  который разойдётся с первым);
- сбой поставщика не роняет страницу: ошибка уходит в лог, поле остаётся пустым;
- на опции с обычным `items` ничего не меняется — механизм включается только при наличии ключа
  `itemsProvider`.

Правило выбора простое: **перечисление режимов — `items`, состав системы — `itemsProvider`.**

## Валидация

Используйте стандартные валидаторы Yii2:

```php
'rules' => [
    ['required'],
    ['string', 'max' => 255],
    ['email'],
    ['integer', 'min' => 0],
    ['boolean'],
    ['in', 'range' => ['value1', 'value2']],
]
```

## Расширение

### Добавить новое хранилище (Redis)

```php
use Besnovatyj\Config\storage\StorageInterface;

class RedisStorage implements StorageInterface
{
    public function get(string $id): ?string { /* ... */ }
    public function getAll(): array { /* ... */ }
    public function save(array $values): bool { /* ... */ }
    public function clear(): bool { /* ... */ }
}
```

Зарегистрируйте в `config/container.php` или `Bootstrap.php`.

### Добавить историю изменений

Расширьте `DatabaseStorage`:

```php
class DatabaseStorageWithHistory extends DatabaseStorage
{
    public function save(array $values): bool
    {
        // Сохранить в таблицу истории
        foreach ($values as $id => $value) {
            $history = new ConfigHistory();
            $history->config_id = $id;
            $history->old_value = $this->get($id);
            $history->new_value = $value;
            $history->save();
        }

        return parent::save($values);
    }
}
```

## Файлы

- `Bootstrap.php` - регистрирует DI и применяет конфигурацию
- `Module.php` - главный класс модуля
- `entities/` - domain модели
- `storage/` - реализации хранилищ
- `repositories/` - работа с хранилищем
- `services/` - бизнес-логика
- `controllers/backend/` - контроллеры админки
- `config/container.php` - DI конфигурация
- `config/schema.php` - схема БД для DatabaseStorage

## Требования

- PHP 8.0+
- Yii2 2.0.45+
- Yii2 DI Container
