# salesrender/plugin-component-api-client

GraphQL API client component for the SalesRender plugin ecosystem. Provides a fault-tolerant HTTP client with automatic retry logic, pagination support, and an abstract iterator for fetching large datasets page by page.

## Installation

```bash
composer require salesrender/plugin-component-api-client
```

## Requirements

- PHP >= 7.4
- Dependencies:

| Package | Version | Purpose |
|---|---|---|
| `salesrender/plugin-component-guzzle` | ^0.3 | Shared Guzzle HTTP client singleton |
| `softonic/graphql-client` | ^1.2 | GraphQL response parsing |
| `adbario/php-dot-notation` | ^2.2 | Dot-notation access to nested arrays |
| `xakepehok/array-graphql` | ^0.0.1 | PHP array to GraphQL query conversion |

## Key Classes

### `ApiClient`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Sends GraphQL queries to the SalesRender API with automatic retry on server errors (5xx). Uses the shared Guzzle instance from [`plugin-component-guzzle`](https://github.com/SalesRender/plugin-component-guzzle).

| Method / Property | Signature | Description |
|---|---|---|
| `__construct` | `(string $endpoint, string $token, int $maxRequestAttempts = 10, int $attemptsDelay = 10)` | Create a client for the given API endpoint and auth token |
| `query` | `(string $query, ?array $variables): Response` | Execute a GraphQL query/mutation and return the parsed response |
| `$lockId` | `public static ?string $lockId = null` | Optional lock ID sent as `X-LOCK-ID` header for order locking |

**Retry behavior:** On a `ServerException` (HTTP 5xx), the client sleeps for `$attemptsDelay` seconds and retries. It performs up to `$maxRequestAttempts` attempts. After exhausting retries, the final request is made without catching exceptions. The request timeout is 60 seconds.

### `ApiFilterSortPaginate`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Value object that encapsulates filters, sorting, and pagination state for API fetcher queries.

| Method | Signature | Description |
|---|---|---|
| `__construct` | `(?array $filters, ?ApiSort $sort, ?int $pageSize)` | Create FSP with filters, optional sort, and page size |
| `getFilters` | `(): ?array` | Return the filters array |
| `getSort` | `(): ?ApiSort` | Return the sort configuration |
| `getPageSize` | `(): ?int` | Return items per page |
| `getPageNumber` | `(): int` | Return the current page number (starts at 1) |
| `setPageNumber` | `(int $pageNumber): void` | Set the current page number |
| `incPageNumber` | `(): void` | Increment the current page number by 1 |

### `ApiSort`

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Value object representing a sort field and direction.

| Method / Constant | Signature | Description |
|---|---|---|
| `__construct` | `(string $field, string $direction)` | Create sort; direction must be `ASC` or `DESC` |
| `getField` | `(): string` | Return the field name to sort by |
| `getDirection` | `(): string` | Return the sort direction |
| `ASC` | `const 'ASC'` | Ascending sort direction |
| `DESC` | `const 'DESC'` | Descending sort direction |

### `ApiFetcherIterator` (abstract)

**Namespace:** `SalesRender\Plugin\Components\ApiClient`

Abstract iterator that automatically paginates through a GraphQL fetcher endpoint. Implements `Iterator` and `Countable`. Subclasses define the query shape and identity extraction.

| Method | Signature | Description |
|---|---|---|
| `__construct` | `(array $fields, ApiClient $client, ApiFilterSortPaginate $fsp, bool $preventPaginationOverlay = true, int $limit = null)` | Create iterator with field selection, client, and FSP |
| `setOnBeforeBatch` | `(callable $onBeforeBatch): void` | Set callback invoked before each page fetch |
| `setOnAfterBatch` | `(callable $onAfterBatch): void` | Set callback invoked after each page is consumed |
| `count` | `(): int` | Return total items count (respecting limit); triggers a count query on first call |
| `current` | `(): mixed` | Return the current item array |
| `key` | `(): string` | Return the identity of the current item |
| `valid` | `(): bool` | Check if the current position is valid |
| `rewind` | `(): void` | Reset the iterator to the first page |

**Abstract methods (must be implemented by subclasses):**

| Method | Signature | Description |
|---|---|---|
| `getQuery` | `(array $fields): string` | Return the GraphQL query string for the given fields |
| `getQueryPath` | `(): string` | Return the dot-notation path to the fetcher in the response (e.g. `'ordersFetcher'`) |
| `getIdentity` | `(array $array): string` | Extract a unique identity string from an item |

**Constants:**

| Constant | Value | Description |
|---|---|---|
| `SORT_LIMIT` | `5000` | Sorting is only applied when the total count is at or below this threshold |

**Pagination overlay prevention:** When `$preventPaginationOverlay` is `true` (default), the iterator tracks seen item identities and filters out duplicates that may appear when data changes between page fetches.

## Usage Examples

### Basic GraphQL Query

From real plugin handlers (`plugin-macros-fields-cleaner`, `plugin-macros-excel`, `plugin-macros-status-changer`):

```php
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use SalesRender\Plugin\Components\ApiClient\ApiClient;
use Adbar\Dot;

$token = GraphqlInputToken::getInstance();
$client = new ApiClient(
    "{$token->getBackendUri()}companies/{$token->getPluginReference()->getCompanyId()}/CRM",
    (string) $token->getOutputToken()
);

// Query
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

From `plugin-macros-status-changer`:

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
        // handle error: $error['message']
    }
}
```

### Using the Lock ID

From `plugin-core-logistic` (`BatchLockTrait`):

```php
use SalesRender\Plugin\Components\Batch\Batch;
use Adbar\Dot;

// Set a lock ID on the API client to lock orders during batch processing
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

### Implementing an OrdersFetcherIterator

From `plugin-core-logistic` and `plugin-macros-example`:

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

### Iterating Over Orders

From `plugin-macros-excel` (`ExcelHandler`):

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

// Get total count (triggers a count-only query on first call)
$total = count($ordersIterator);

// Iterate through all pages automatically
foreach ($ordersIterator as $id => $order) {
    // $id is the order ID (from getIdentity)
    // $order is the full order data array
}
```

### Creating ApiFilterSortPaginate

From `plugin-core` (`BatchPrepareAction`):

```php
use SalesRender\Plugin\Components\ApiClient\ApiFilterSortPaginate;
use SalesRender\Plugin\Components\ApiClient\ApiSort;

$filters = ['include' => ['ids' => [1, 2, 3]]];
$sort = new ApiSort('createdAt', ApiSort::DESC);
$fsp = new ApiFilterSortPaginate($filters, $sort, 100);

// Or without sort and filters
$fsp = new ApiFilterSortPaginate(null, null, 50);
```

## API Reference

### How ApiFetcherIterator Builds Variables

The iterator automatically constructs GraphQL variables from the `ApiFilterSortPaginate` object:

```json
{
  "pagination": {
    "pageNumber": 1,
    "pageSize": 100
  },
  "filters": { "...your filters..." },
  "sort": {
    "field": "createdAt",
    "direction": "DESC"
  }
}
```

- **Pagination** is always included.
- **Filters** are included only when `getFilters()` returns a non-empty value.
- **Sort** is included only when the total count is <= `SORT_LIMIT` (5000) and sort is configured. This prevents expensive sorted queries on large datasets.

### Getting ApiClient from Batch

The `Batch` model (from [`plugin-component-batch`](https://github.com/SalesRender/plugin-component-batch)) provides a convenience method that constructs an `ApiClient` pointed at the correct company CRM endpoint:

```php
$client = $batch->getApiClient();
// Equivalent to:
// new ApiClient(
//     $token->getBackendUri() . "companies/{$token->getCompanyId()}/CRM",
//     (string) $token->getOutputToken()
// );
```

## See Also

- [salesrender/plugin-component-batch](https://github.com/SalesRender/plugin-component-batch) -- Batch processing (uses ApiClient and ApiFilterSortPaginate)
- [salesrender/plugin-component-guzzle](https://github.com/SalesRender/plugin-component-guzzle) -- Shared Guzzle HTTP client singleton
- [softonic/graphql-client](https://packagist.org/packages/softonic/graphql-client) -- Underlying GraphQL response builder
- [xakepehok/array-graphql](https://packagist.org/packages/xakepehok/array-graphql) -- PHP array to GraphQL field selection converter
