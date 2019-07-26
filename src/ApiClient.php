<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 15:18
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


use Softonic\GraphQL\Client;
use Softonic\GraphQL\ClientBuilder;
use Softonic\GraphQL\Response;

class ApiClient
{
    /**
     * @var string
     */
    private $endpoint;
    /**
     * @var string
     */
    private $token;
    /**
     * @var Client
     */
    private $client;

    public function __construct(string $endpoint, string $token)
    {
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->client = ClientBuilder::build($endpoint, [
            'headers' => [
                'User-Agent' => 'lv-plugin-api-client',
                'Authorization' => "Bearer {$token}",
            ],
        ]);
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    public function query(string $query, array $variables): Response
    {
        return $this->client->query($query, $variables);
    }

}