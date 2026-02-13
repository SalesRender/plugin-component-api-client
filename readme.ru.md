# salesrender/plugin-component-api-client

Компонент GraphQL API клиента для экосистемы плагинов SalesRender. Предоставляет отказоустойчивый HTTP-клиент с автоматическими повторными попытками, поддержку пагинации и абстрактный iterator для постраничного извлечения больших объемов данных.

## Установка

```bash
composer require salesrender/plugin-component-api-client
```

## Требования

- PHP >= 7.4
- Зависимости:

| Пакет | Версия | Назначение |
|---|---|---|
| `salesrender/plugin-component-guzzle` | ^0.3 | Singleton общего HTTP-клиента Guzzle |
| `softonic/graphql-client` | ^1.2 | Разбор GraphQL-ответов |
| `adbario/php-dot-notation` | ^2.2 | Доступ к вложенным массивам через dot-нотацию |
| `xakepehok/array-graphql` | ^0.0.1 | Конвертация PHP-массивов в GraphQL-запросы |

## Основные классы

### `ApiClient`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Отправляет GraphQL-запросы к API SalesRender с автоматическими повторными попытками при серверных ошибках (5xx). Использует общий экземпляр Guzzle из [`plugin-component-guzzle`](https://github.com/SalesRender/plugin-component-guzzle).

| Метод / Свойство | Сигнатура | Описание |
|---|---|---|
| `__construct` | `(string $endpoint, string $token, int $maxRequestAttempts = 10, int $attemptsDelay = 10)` | Создать клиент для указанного API endpoint и токена авторизации |
| `query` | `(string $query, ?array $variables): Response` | Выполнить GraphQL query/mutation и вернуть разобранный ответ |
| `$lockId` | `public static ?string $lockId = null` | Опциональный ID блокировки, передаваемый в заголовке `X-LOCK-ID` |

**Логика повторных попыток:** При получении `ServerException` (HTTP 5xx) клиент ожидает `$attemptsDelay` секунд и повторяет запрос. Выполняется до `$maxRequestAttempts` попыток. После исчерпания попыток последний запрос выполняется без перехвата исключений. Таймаут запроса -- 60 секунд.

### `ApiFilterSortPaginate`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Объект-значение, инкапсулирующий фильтры, сортировку и состояние пагинации для запросов к API.

| Метод | Сигнатура | Описание |
|---|---|---|
| `__construct` | `(?array $filters, ?ApiSort $sort, ?int $pageSize)` | Создать FSP с фильтрами, опциональной сортировкой и размером страницы |
| `getFilters` | `(): ?array` | Получить массив фильтров |
| `getSort` | `(): ?ApiSort` | Получить конфигурацию сортировки |
| `getPageSize` | `(): ?int` | Получить количество элементов на странице |
| `getPageNumber` | `(): int` | Получить текущий номер страницы (начинается с 1) |
| `setPageNumber` | `(int $pageNumber): void` | Установить текущий номер страницы |
| `incPageNumber` | `(): void` | Увеличить текущий номер страницы на 1 |

### `ApiSort`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Объект-значение, представляющий поле сортировки и направление.

| Метод / Константа | Сигнатура | Описание |
|---|---|---|
| `__construct` | `(string $field, string $direction)` | Создать сортировку; направление должно быть `ASC` или `DESC` |
| `getField` | `(): string` | Получить имя поля для сортировки |
| `getDirection` | `(): string` | Получить направление сортировки |
| `ASC` | `const 'ASC'` | Сортировка по возрастанию |
| `DESC` | `const 'DESC'` | Сортировка по убыванию |

### `ApiFetcherIterator` (abstract)

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Абстрактный iterator, автоматически выполняющий пагинацию через GraphQL fetcher endpoint. Реализует интерфейсы `Iterator` и `Countable`. Подклассы определяют форму запроса и извлечение идентификатора.

| Метод | Сигнатура | Описание |
|---|---|---|
| `__construct` | `(array $fields, ApiClient $client, ApiFilterSortPaginate $fsp, bool $preventPaginationOverlay = true, int $limit = null)` | Создать iterator с выборкой полей, клиентом и FSP |
| `setOnBeforeBatch` | `(callable $onBeforeBatch): void` | Установить callback, вызываемый перед загрузкой каждой страницы |
| `setOnAfterBatch` | `(callable $onAfterBatch): void` | Установить callback, вызываемый после обработки каждой страницы |
| `count` | `(): int` | Получить общее количество элементов (с учетом лимита); при первом вызове выполняет запрос для подсчета |
| `current` | `(): mixed` | Получить текущий элемент (массив) |
| `key` | `(): string` | Получить идентификатор текущего элемента |
| `valid` | `(): bool` | Проверить, допустима ли текущая позиция |
| `rewind` | `(): void` | Сбросить iterator на первую страницу |

**Абстрактные методы (обязательны к реализации в подклассах):**

| Метод | Сигнатура | Описание |
|---|---|---|
| `getQuery` | `(array $fields): string` | Вернуть строку GraphQL-запроса для указанных полей |
| `getQueryPath` | `(): string` | Вернуть путь в dot-нотации к fetcher в ответе (например, `'ordersFetcher'`) |
| `getIdentity` | `(array $array): string` | Извлечь уникальный идентификатор из элемента |

**Константы:**

| Константа | Значение | Описание |
|---|---|---|
| `SORT_LIMIT` | `5000` | Сортировка применяется только когда общее количество не превышает этот порог |

**Предотвращение наложения пагинации:** Когда `$preventPaginationOverlay` установлен в `true` (по умолчанию), iterator отслеживает уже встреченные идентификаторы элементов и фильтрует дубликаты, которые могут появиться при изменении данных между загрузками страниц.

## Примеры использования

### Базовый GraphQL-запрос

Из реальных обработчиков плагинов (`plugin-macros-fields-cleaner`, `plugin-macros-excel`, `plugin-macros-status-changer`):

```php
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use SalesRender\Plugin\Components\ApiClient\ApiClient;
use Adbar\Dot;

$token = GraphqlInputToken::getInstance();
$client = new ApiClient(
    "{$token->getBackendUri()}companies/{$token->getPluginReference()->getCompanyId()}/CRM",
    (string) $token->getOutputToken()
);

// Запрос
$query = <<<QUERY
query {
  company {
    currency
  }
}
QUERY;

$response = $client->query($query, []);
$data = new Dot($response->getData());
$currency = $data->get('company.currency');
```

### GraphQL Mutation

Из `plugin-macros-status-changer`:

```php
$mutation = <<<QUERY
mutation updateOrder(\$input: UpdateOrderInput!) {
  orderMutation {
    updateOrder(input: \$input) {
      status {
        id
      }
    }
  }
}
QUERY;

$response = $client->query($mutation, [
    'input' => [
        'id' => $orderId,
        'statusId' => $targetStatus,
    ]
]);

if ($response->hasErrors()) {
    foreach ($response->getErrors() as $error) {
        // обработка ошибки: $error['message']
    }
}
```

### Использование Lock ID

Из `plugin-core-logistic` (`BatchLockTrait`):

```php
use SalesRender\Plugin\Components\Batch\Batch;
use Adbar\Dot;

// Устанавливаем lock ID на API-клиенте для блокировки заказов при пакетной обработке
$batch->getApiClient()::$lockId = $this->lockId;
$client = $batch->getApiClient();

$query = '
    mutation($id: ID!, $timeout: Int!) {
      lockMutation {
        lockEntity(input: { entity: { entity: Order, id: $id }, timeout: $timeout })
      }
    }
';

$response = new Dot($client->query($query, [
    'id' => $orderId,
    'timeout' => $timeout,
])->getData());

$isLocked = $response->get('lockMutation.lockEntity', false);
```

### Реализация OrdersFetcherIterator

Из `plugin-core-logistic` и `plugin-macros-example`:

```php
use SalesRender\Plugin\Components\ApiClient\ApiFetcherIterator;
use XAKEPEHOK\ArrayGraphQL\ArrayGraphQL;

class OrdersFetcherIterator extends ApiFetcherIterator
{
    protected function getQuery(array $fields): string
    {
        return '
            query($pagination: Pagination!, $filters: OrderSearchFilter, $sort: OrderSort) {
                ordersFetcher(pagination: $pagination, filters: $filters, sort: $sort) '
                . ArrayGraphQL::convert($fields) .
            '}
        ';
    }

    protected function getQueryPath(): string
    {
        return 'ordersFetcher';
    }

    protected function getIdentity(array $array): string
    {
        return $array['id'];
    }
}
```

### Итерация по заказам

Из `plugin-macros-excel` (`ExcelHandler`):

```php
use SalesRender\Plugin\Components\Batch\Batch;

/** @var Batch $batch */
$orderFields = [
    'orders' => [
        'id',
        'status' => ['id'],
        'createdAt',
        'cart' => ['total'],
    ]
];

$ordersIterator = new OrdersFetcherIterator(
    $orderFields,
    $batch->getApiClient(),
    $batch->getFsp()
);

// Получение общего количества (при первом вызове выполняется отдельный запрос)
$total = count($ordersIterator);

// Автоматическая итерация по всем страницам
foreach ($ordersIterator as $id => $order) {
    // $id -- идентификатор заказа (из getIdentity)
    // $order -- полный массив данных заказа
}
```

### Создание ApiFilterSortPaginate

Из `plugin-core` (`BatchPrepareAction`):

```php
use SalesRender\Plugin\Components\ApiClient\ApiFilterSortPaginate;
use SalesRender\Plugin\Components\ApiClient\ApiSort;

$filters = ['include' => ['ids' => [1, 2, 3]]];
$sort = new ApiSort('createdAt', ApiSort::DESC);
$fsp = new ApiFilterSortPaginate($filters, $sort, 100);

// Или без сортировки и фильтров
$fsp = new ApiFilterSortPaginate(null, null, 50);
```

## Справочник API

### Формирование переменных в ApiFetcherIterator

Iterator автоматически формирует GraphQL-переменные из объекта `ApiFilterSortPaginate`:

```json
{
  "pagination": {
    "pageNumber": 1,
    "pageSize": 100
  },
  "filters": { "...ваши фильтры..." },
  "sort": {
    "field": "createdAt",
    "direction": "DESC"
  }
}
```

- **Pagination** включается всегда.
- **Filters** включаются только когда `getFilters()` возвращает непустое значение.
- **Sort** включается только когда общее количество записей <= `SORT_LIMIT` (5000) и сортировка задана. Это предотвращает ресурсоемкие запросы с сортировкой на больших наборах данных.

### Получение ApiClient из Batch

Модель `Batch` (из [`plugin-component-batch`](https://github.com/SalesRender/plugin-component-batch)) предоставляет удобный метод для создания `ApiClient`, настроенного на нужный CRM endpoint компании:

```php
$client = $batch->getApiClient();
// Эквивалентно:
// new ApiClient(
//     $token->getBackendUri() . "companies/{$token->getCompanyId()}/CRM",
//     (string) $token->getOutputToken()
// );
```

## Смотрите также

- [salesrender/plugin-component-batch](https://github.com/SalesRender/plugin-component-batch) -- Пакетная обработка (использует ApiClient и ApiFilterSortPaginate)
- [salesrender/plugin-component-guzzle](https://github.com/SalesRender/plugin-component-guzzle) -- Singleton общего HTTP-клиента Guzzle
- [softonic/graphql-client](https://packagist.org/packages/softonic/graphql-client) -- Библиотека разбора GraphQL-ответов
- [xakepehok/array-graphql](https://packagist.org/packages/xakepehok/array-graphql) -- Конвертер PHP-массивов в GraphQL-выборку полей
