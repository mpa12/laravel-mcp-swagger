# Laravel MCP Swagger

Статья на Хабр - https://habr.com/ru/articles/1089476/

MCP-сервер на Laravel, который открывает Claude Code доступ к документации Swagger 2.0: полнотекстовый поиск по операциям и схемам, детали операций, JSON-схемы и операции по тегам.

- **Backend**: Laravel 13 + laravel/mcp + laravel/scout (драйвер `database`, full-text поиск)
- **БД**: PostgreSQL
- **Источник данных**: Swagger 2.0-спецификация (по умолчанию PetStore)

---

## Guide-line: создание проекта с нуля

### 1. Установка Laravel

```shell
composer create-project laravel/laravel laravel-mcp-swagger
cd laravel-mcp-swagger
```

Настроить `.env` (в примере используется PostgreSQL):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_mcp_swagger
DB_USERNAME=root
DB_PASSWORD=
```

Также добавить две переменные, которые понадобятся дальше:

```env
SCOUT_DRIVER=database
SWAGGER_URL=https://petstore.swagger.io/v2/swagger.json
```

### 2. Установка зависимостей

```shell
# MCP-сервер
composer require laravel/mcp

# Полнотекстовый поиск (драйвер database, без внешних движков)
composer require laravel/scout
```

Драйвер `database` в Scout использует нативные full-text индексы БД (`tsvector` для PostgreSQL), ничего дополнительно поднимать не нужно.

### 3. Парсинг Swagger 2.0 + сохранение данных в БД

#### 3.1. Миграция таблицы `api_docs`

```shell
php artisan make:migration create_api_docs_table
```

```php
Schema::create('api_docs', function (Blueprint $table) {
    $table->id();
    $table->string('type')->index(); // operation|definition|tag
    $table->string('doc_key')->unique();
    $table->string('operation_id')->nullable()->index();
    $table->string('method')->nullable();
    $table->string('path')->nullable();
    $table->string('title');
    $table->longText('content');
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->fullText(['title', 'content']);
});
```

Что важно:

- `type`: тип документа, `operation` (одна запись на path × HTTP-метод), `definition` (одна запись на `definitions`), `tag` (описания тегов).
- `doc_key`: уникальный ключ вида `operation:updatePet` (или `operation:GET:/pet/{petId}`, если `operationId` не задан), `definition:Pet`, `tag:pet`.
- fulltext-индекс по `title` + `content` служит основой поиска Scout-драйвера `database`.

#### 3.2. Модель `ApiDoc`

```shell
php artisan make:model ApiDoc
```

Модель подключает трейт `Searchable` из Scout и помечает поисковые поля атрибутом `#[SearchUsingFullText]`. JSON-поле `meta` кастуется в DTO через собственный каст:

```php
use App\Casts\MetaDtoCast;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

final class ApiDoc extends Model
{
    use Searchable;

    protected function casts(): array
    {
        return ['meta' => MetaDtoCast::class];
    }

    #[SearchUsingFullText(['title', 'content'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
```

`MetaDtoCast` (в `app/Casts/`) на чтении декодирует JSON в `App\Dto\MetaDto` (объект с массивом `tags`), на записи сериализует DTO или массив обратно в JSON.

#### 3.3. DTO

Аргументы инструментов и `meta`-поле модели оформлены DTO в `app/Dto/`:

- `MetaDto`: типизированная обёртка над `meta` (`tags`).
- `PetStoreSearchDto`, `PetStoreOperationDetailsDto`, `PetStoreDefinitionDetailsDto`, `PetStoreTagDetailsDto`: неизменяемые объекты с фабрикой `fromArray()`, принимающей проверенные данные из `$request->validate()`. Нормализация (trim, strtoupper для `method`) сосредоточена в DTO, сервис и инструмент работают уже с нормализованными значениями.

#### 3.4. Конфиг `swagger_url`

В `config/app.php`:

```php
'swagger_url' => env('SWAGGER_URL'),
```

#### 3.5. Команда импорта `swagger:import`

```shell
php artisan make:command SwaggerImport
```

Логика команды (`app/Console/Commands/SwaggerImport.php`):

1. Забирает JSON по `SWAGGER_URL` через `file_get_contents`. Принимает только документы с `swagger: "2.0"` без маркера `openapi`; другой формат отклоняется до удаления существующих записей.
2. **Резолвит `$ref`**: рекурсивно заменяет узлы `{"$ref": "#/definitions/Pet"}` на сами объекты (с ограничением глубины и защитой от циклов по цепочке указателей). После этого все вложенные схемы доступны напрямую, а не по ссылкам, что упрощает выдачу для LLM.
3. Очищает `api_docs` и импортирует три типа записей через `updateOrCreate` по `doc_key`:
   - **operations**: на каждый `path` × метод (`get/post/put/delete/patch`) создаётся запись с `title = "GET /pet/{petId} — Find pet by ID"`, `content` в виде pretty-print JSON всей операции (именно он участвует в полнотекстовом поиске и возвращается инструментом деталей) и `meta.tags` для фильтрации по тегам через `whereJsonContains`. Если у операции нет `operationId`, ключ строится из метода и пути.
   - **definitions**: на каждую схему из `definitions`, `content` хранит pretty-print JSON схемы.
   - **tags**: на каждый тег верхнего уровня, `content` хранит описание тега.

Команда объявлена атрибутами PHP 8 (`#[Signature]`, `#[Description]`) вместо свойства `$signature`.

```shell
php artisan migrate
php artisan swagger:import
```

### 4. Создание MCP-сервера

```shell
php artisan make:mcp-server PetStoreDocsServer
```

Класс создаётся в `app/Mcp/Servers/`. Регистрируем инструменты и пишем инструкции для LLM:

```php
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('petstore-docs')]
#[Version('1.0.0')]
#[Instructions('Документация PetStore API (Swagger 2.0): поиск по операциям, схемам и тегам, детали операций и схем.')]
final class PetStoreDocsServer extends Server
{
    protected array $tools = [
        PetStoreSearchTool::class,
        PetStoreOperationDetailsTool::class,
        PetStoreDefinitionsDetailsTool::class,
        PetStoreTagDetailsTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
```

Регистрируем HTTP-эндпоинт в `routes/ai.php` (этот файл автозагружается провайдером laravel/mcp и публикуется пакетом, создавать вручную не нужно):

```php
use App\Mcp\Servers\PetStoreDocsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/petstore-docs', PetStoreDocsServer::class);
```

> Альтернатива для stdio-транспорта: `Mcp::local('petstore-docs', PetStoreDocsServer::class)` и запуск через `php artisan mcp:start petstore-docs`.

### 5. Создание Tools

```shell
php artisan make:mcp-tool PetStoreSearchTool
```

Каждый инструмент описан классом в `app/Mcp/Tools/` с тремя частями:

| Часть | Назначение |
|---|---|
| `#[Description]`, `#[IsReadOnly]` | Описание для LLM и аннотация «только чтение» |
| `schema(JsonSchema $schema)` | JSON-схема входных аргументов (то, что видит LLM) |
| `handle(Request $request, ...)` | Валидация + логика + ответ |

Архитектурный приём: `handle()` сразу валидирует аргументы через `$request->validate()` (правила + человекочитаемые сообщения об ошибках на русском: LLM получит их и сможет исправить запрос), затем передаёт данные в DTO и сервис `ApiDocService`, где сосредоточена вся работа с БД.

#### 5.1. `PetStoreSearchTool`, полнотекстовый поиск

Аргументы: `query` (обязательный), `type` (`operation|definition|tag`), `method` (`GET|POST|PUT|DELETE|PATCH`), `path` (точное совпадение), `limit` (по умолчанию 10, максимум 50). Фильтры `method` и `path` применимы только к операциям. Возвращает `Response::structured()`: массив найденных записей с `operationId`, `method`, `path`, `title` и первыми 200 символами описания. Поиск выполняется в `ApiDocService`:

```php
// app/Services/ApiDocService.php
public function search(PetStoreSearchDto $data): Collection
{
    return ApiDoc::search($data->query)
        ->take($data->limit)
        ->when($data->type, fn (\Laravel\Scout\Builder $query) => $query->where('type', $data->type))
        ->when($data->method, fn (\Laravel\Scout\Builder $query) => $query->where('method', $data->method))
        ->when($data->path, fn (\Laravel\Scout\Builder $query) => $query->where('path', $data->path))
        ->get();
}
```

Вся работа с БД сосредоточена в `ApiDocService`; инструменты остаются тонкими: валидация → DTO → сервис → `Response`.

#### 5.2. `PetStoreOperationDetailsTool`, детали операции

Идентификация операции: `operationId` **или** пара `method` + `path` (правила `required_without_all` / `required_without`). Возвращает pretty-print JSON операции целиком: тот же `content`, что участвует в поиске. Ошибки отдаются через `Response::error()`.

#### 5.3. `PetStoreDefinitionsDetailsTool`, JSON-схема по имени

Аргумент `name`, поиск по `doc_key = "definition:{name}"`, ответ: исходный pretty-print JSON схемы.

#### 5.4. `PetStoreTagDetailsTool`, операции по тегу

Фильтрация по JSON-полю: `whereJsonContains('meta->tags', $tag)`, сортировка по пути, ответ: структуры с `method`, `path` и `title` вида `GET /pet/{petId} — Find pet by ID`.

### 6. Использование: подключение к Claude Code

Поднять приложение и зарегистрировать сервер в Claude Code:

```shell
php artisan serve
claude mcp add --transport http petstore-docs http://localhost:8000/mcp/petstore-docs --scope project
```

Флаг `--scope project` записывает конфигурацию в `.mcp.json` проекта, ею можно поделиться с командой.

Для отладки инструментов есть встроенный инспектор:

```shell
php artisan mcp:inspector mcp/petstore-docs
```

Теперь Claude может отвечать на вопросы про API, вызывая инструменты:

> «Найди в PetStore операции для работы с питомцами и покажи детали `getPetById`»

### 7. Чек-лист новых изменений

После правки Swagger-документации:

```shell
php artisan swagger:import   # переимпортирует данные (старые удаляются)
```

Изменения в коде инструментов подхватываются автоматически (транспорты HTTP и stdio не требуют перезапуска конфигурации сервера в Claude Code).
