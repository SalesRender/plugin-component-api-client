<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 15:18
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace SalesRender\Plugin\Components\ApiClient;


use GuzzleHttp\Client;
use SalesRender\Plugin\Components\Guzzle\Guzzle;
use Softonic\GraphQL\Response;
use Softonic\GraphQL\ResponseBuilder;

class ApiClient
{

    public static ?string $lockId = null;

    private string $endpoint;
    private string $token;
    private Client $client;
    private ResponseBuilder $responseBuilder;

    public function __construct(string $endpoint, string $token)
    {
        $this->client = Guzzle::getInstance();
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->responseBuilder = new ResponseBuilder();
    }

    public function query(string $query, ?array $variables): Response
    {
        $headers = $this->client->getConfig('headers');
        $headers['Authorization'] = $this->token;

        if (!empty(self::$lockId)) {
            $headers['X-LOCK-ID'] = self::$lockId;
        }

        $options = [
            'headers' => $headers,
            'json' => [
                'query' => $query,
            ],
        ];

        if (!is_null($variables)) {
            $options['json']['variables'] = $variables;
        }

        $response = Guzzle::getInstance()->request('POST', $this->endpoint, $options);
        return $this->responseBuilder->build($response);
    }

}