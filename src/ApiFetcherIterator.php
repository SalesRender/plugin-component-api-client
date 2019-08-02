<?php
/**
 * Created for plugin-api-client
 * Datetime: 30.07.2019 17:52
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


use Adbar\Dot;
use GuzzleHttp\Exception\GuzzleException;
use Leadvertex\Plugin\Components\Process\Components\Error;
use Leadvertex\Plugin\Components\Process\Components\Handled;
use Leadvertex\Plugin\Components\Process\Components\Init;
use Leadvertex\Plugin\Components\Process\Components\Skipped;
use Leadvertex\Plugin\Components\Process\Exceptions\AlreadyInitializedException;
use Leadvertex\Plugin\Components\Process\Exceptions\NotInitializedException;
use Leadvertex\Plugin\Components\Process\Process;

abstract class ApiFetcherIterator
{

    /**
     * @var ApiClient
     */
    private $client;
    /**
     * @var ApiFilterSortPaginate
     */
    private $fsp;
    /**
     * @var Process
     */
    private $process;

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

    /**
     * @param array $fields
     * @param callable $handler
     * @throws GuzzleException
     * @throws AlreadyInitializedException
     * @throws NotInitializedException
     */
    public function iterator(array $fields, callable $handler)
    {
        $itemsCount = $this->init();
        $pageNumber = 1;
        $ids = [];
        $query = $this->getQuery($fields);
        do {
            $handled = 0;
            $skipped = 0;
            $errors = [];

            $variables = $this->getVariables($pageNumber);
            $response = new Dot($this->client->query($query, $variables)->getData());
            $items = $response->get($this->getQueryPath() . "." . key($fields));
            foreach ($items as $item) {

                //Prevent multiple handling
                $identity = $this->getIdentity($item);
                if (isset($ids[$identity])) {
                    $skipped++;
                    continue;
                }
                $ids[$identity] = true;

                //Check handling result
                $result = $handler($item);
                if ($result instanceof Handled) {
                    $handled+= $result->getCount();
                } elseif ($result instanceof Skipped) {
                    $skipped+= $result->getCount();
                } elseif ($result instanceof Error) {
                    $errors[] = $result;
                }
            }

            //Send webhooks
            $this->process->handleWebhook(new Handled($handled));
            $this->process->skipWebhook(new Skipped($skipped));
            $this->process->errorWebhook($errors);

            $pageNumber++;
        } while (!empty($items));

        //Calc skipped items
        $this->process->skipWebhook(new Skipped($itemsCount - count($ids)));
    }

    /**
     * @return int
     * @throws GuzzleException
     * @throws AlreadyInitializedException
     */
    private function init(): int
    {
        $query = $this->getQuery(['pageInfo' => ['itemsCount']]);
        $variables = $this->getVariables(1);

        $response = new Dot($this->client->query($query, $variables)->getData());
        $itemsCount = $response->get("{$this->getQueryPath()}.pageInfo.itemsCount");
        $this->process->initWebhook(new Init($itemsCount));

        return $itemsCount;
    }

    /**
     * @param int $pageNumber
     * @return array
     */
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