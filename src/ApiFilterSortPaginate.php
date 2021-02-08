<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 19:00
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


class ApiFilterSortPaginate
{

    private ?array $filters;

    private ?ApiSort $sort;

    private ?int $pageSize;

    private int $pageNumber = 1;

    public function __construct(?array $filters, ?ApiSort $sort, ?int $pageSize)
    {
        $this->filters = $filters;
        $this->pageSize = $pageSize;
        $this->sort = $sort;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function getSort(): ?ApiSort
    {
        return $this->sort;
    }

    public function getPageSize(): ?int
    {
        return $this->pageSize;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function setPageNumber(int $pageNumber): void
    {
        $this->pageNumber = $pageNumber;
    }

    public function incPageNumber(): void
    {
        $this->pageNumber++;
    }


}