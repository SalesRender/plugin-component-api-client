<?php
/**
 * Created for plugin-api-client
 * Datetime: 30.07.2019 17:52
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


use Adbar\Dot;
use Leadvertex\Plugin\Components\Process\Process;

abstract class ApiFetcherIterator
{

    private ApiClient $client;

    private ApiFilterSortPaginate $fsp;

    private Process $process;

    public function __construct(Process $process, ApiClient $client, ApiFilterSortPaginate $fsp)
    {
        $this->client = $client;
        $this->fsp = $fsp;
        $this->process = $process;
    }

    abstract protected function getQuery(array $body): string;

    /**
     * Dot-notation string to query body
     * @return string
     */
    abstract protected function getQueryPath(): string;

    abstract protected function getIdentity(array $array): string;

    public function iterator(array $fields, callable $handler): void
    {
        $this->init();
        $pageNumber = 1;
        $ids = [];
        $query = $this->getQuery($fields);
        do {
            $variables = $this->getVariables($pageNumber);
            $response = new Dot($this->client->query($query, $variables)->getData());
            $items = $response->get($this->getQueryPath() . "." . key($fields));
            foreach ($items as $item) {

                //Prevent multiple handling
                $identity = $this->getIdentity($item);
                if (isset($ids[$identity])) {
                    continue;
                }
                $ids[$identity] = true;

                $handler($item, $this->process);
            }

            $this->process->save();

            $pageNumber++;
        } while (!empty($items));
    }

    private function init(): int
    {
        $query = $this->getQuery(['pageInfo' => ['itemsCount']]);
        $variables = $this->getVariables(1);

        $response = new Dot($this->client->query($query, $variables)->getData());
        $itemsCount = $response->get("{$this->getQueryPath()}.pageInfo.itemsCount");
        $this->process->initialize($itemsCount);

        return $itemsCount;
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