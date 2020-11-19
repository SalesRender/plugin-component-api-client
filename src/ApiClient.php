<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 15:18
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace Leadvertex\Plugin\Components\ApiClient;


use Leadvertex\Plugin\Components\Guzzle\Guzzle;
use Softonic\GraphQL\Client;
use Softonic\GraphQL\ClientBuilder;
use Softonic\GraphQL\Response;

class ApiClient
{

    private string $endpoint;

    private string $token;

    private Client $client;

    public function __construct(string $endpoint, string $token)
    {
        $this->endpoint = $endpoint;
        $this->token = $token;
        $guzzle = Guzzle::getInstance();

        $headers = $guzzle->getConfig('headers');
        $this->client = ClientBuilder::build($endpoint, [
            'headers' => array_merge($headers, [
                'Authorization' => $token,
            ]),
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function query(string $query, array $variables): Response
    {
        return $this->client->query($query, $variables);
    }

}