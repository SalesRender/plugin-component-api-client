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

    public static ?string $lockId = null;

    private Client $client;

    public function __construct(string $endpoint, string $token)
    {
        $guzzle = Guzzle::getInstance();

        $headers = $guzzle->getConfig('headers');
        $headers['Authorization'] = $token;

        if (!empty(self::$lockId)) {
            $headers['X-LOCK-ID'] = self::$lockId;
        }

        $this->client = ClientBuilder::build($endpoint, ['headers' => $headers]);
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