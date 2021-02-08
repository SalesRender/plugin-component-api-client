<?php
/**
 * Created for plugin-api-client
 * Datetime: 30.07.2019 17:52
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


use Adbar\Dot;
use Countable;
use Iterator;

abstract class ApiFetcherIterator implements Iterator, Countable
{

    /** @var callable */
    protected $onBeforeBatch;

    /** @var callable */
    protected $onAfterBatch;

    protected ApiClient $client;

    protected ApiFilterSortPaginate $fsp;

    private array $fields;

    private bool $preventPaginationOverlay;

    private array $identities = [];

    private array $currentArray = [];

    private int $currentKey = 0;

    private int $_count;

    private string $_query;

    /**
     * ApiFetcherIterator constructor.
     * @param array $fields, e.g. ['orders' => ['id', 'status' => ['id']]], @see https://github.com/XAKEPEHOK/ArrayGraphQL
     * @param ApiClient $client
     * @param ApiFilterSortPaginate $fsp
     * @param bool $preventPaginationOverlay
     */
    public function __construct(array $fields, ApiClient $client, ApiFilterSortPaginate $fsp, bool $preventPaginationOverlay = true)
    {
        $this->fields = $fields;
        $this->client = $client;
        $this->fsp = $fsp;
        $this->preventPaginationOverlay = $preventPaginationOverlay;
        $this->onBeforeBatch = function () {};
        $this->onAfterBatch = function () {};
    }

    public function setOnBeforeBatch(callable $onBeforeBatch): void
    {
        $this->onBeforeBatch = $onBeforeBatch;
    }

    public function setOnAfterBatch(callable $onAfterBatch): void
    {
        $this->onAfterBatch = $onAfterBatch;
    }

    /**
     * Example:
     *
     * return 'query($pagination: Pagination!, $filters: OrderFilter, $sort: OrderSort) {
     *      ordersFetcher(pagination: $pagination, filters: $filters, sort: $sort) ' . ArrayGraphQL::convert($fields) .
     * '}';
     *
     * @param array $fields
     * @return string
     */
    abstract protected function getQuery(array $fields): string;

    /**
     * @return string dot-notation string to fetcher (Valid: 'ordersFetcher'; INVALID: 'ordersFetcher.orders')
     */
    abstract protected function getQueryPath(): string;

    abstract protected function getIdentity(array $array): string;

    public function count(): int
    {
        if (!isset($this->_count)) {
            $query = $this->getQuery(['pageInfo' => ['itemsCount']]);
            $variables = $this->getVariables(1);

            $response = new Dot($this->client->query($query, $variables)->getData());
            $this->_count = (int) $response->get("{$this->getQueryPath()}.pageInfo.itemsCount");
        }

        return $this->_count;
    }

    public function current()
    {
        return $this->currentArray[$this->currentKey];
    }

    public function next(): void
    {
        $this->currentKey++;
        if ($this->currentKey == count($this->currentArray)) {
            $this->fsp->incPageNumber();
            ($this->onAfterBatch)($this->currentArray);
            ($this->onBeforeBatch)();
            $this->fetchNext();
        }
    }

    public function key()
    {
        $data = $this->currentArray[$this->currentKey];
        return $this->getIdentity($data);
    }

    public function valid(): bool
    {
        return isset($this->currentArray[$this->currentKey]);
    }

    public function rewind(): void
    {
        $this->fsp->setPageNumber(1);
        $this->identities = [];
        ($this->onBeforeBatch)();
        $this->fetchNext();
    }

    private function fetchNext(): void
    {
        if (!isset($this->_query)) {
            $this->_query = $this->getQuery($this->fields);
        }

        $variables = $this->getVariables($this->fsp->getPageNumber());
        $response = new Dot($this->client->query($this->_query, $variables)->getData());

        $this->currentKey = 0;
        $this->currentArray = $response->get($this->getQueryPath() . "." . key($this->fields));

        if ($this->preventPaginationOverlay) {
            $this->currentArray = array_values(array_filter($this->currentArray, function (array $data) {
                $id = $this->getIdentity($data);
                if ($isNew = !isset($this->identities[$id])) {
                    $this->identities[$id] = true;
                }
                return $isNew;
            }));
        }
    }

    private function getVariables(int $pageNumber): array
    {
        $fsp = [
            'pagination' => [
                'pageNumber' => $pageNumber,
                'pageSize' => $this->fsp->getPageSize()
            ]
        ];

        if ($this->fsp->getFilters()) {
            $fsp['filters'] = $this->fsp->getFilters();
        }

        if ($this->fsp->getSort()) {
            $fsp['sort'] = [
                'field' => $this->fsp->getSort()->getField(),
                'direction' => $this->fsp->getSort()->getDirection(),
            ];
        }

        return $fsp;
    }

}