<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 19:00
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


class ApiFilterSortPaginate
{

    /**
     * @var array|null
     */
    private $filters;
    /**
     * @var ApiSort|null
     */
    private $sort;
    /**
     * @var int|null
     */
    private $pageSize;

    public function __construct(?array $filters, ?ApiSort $sort, ?int $pageSize)
    {
        $this->filters = $filters;
        $this->pageSize = $pageSize;
        $this->sort = $sort;
    }

    /**
     * @return array|null
     */
    public function getFilters(): ?array
    {
        return $this->filters;
    }

    /**
     * @return int|null
     */
    public function getPageSize(): ?int
    {
        return $this->pageSize;
    }

    /**
     * @return ApiSort|null
     */
    public function getSort(): ?ApiSort
    {
        return $this->sort;
    }


}