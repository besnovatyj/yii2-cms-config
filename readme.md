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
