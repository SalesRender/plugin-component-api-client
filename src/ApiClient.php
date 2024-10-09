<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 15:18
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace SalesRender\Plugin\Components\ApiClient;


use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use SalesRender\Plugin\Components\Guzzle\Guzzle;
use Softonic\GraphQL\Response;
use Softonic\GraphQL\ResponseBuilder;
use Throwable;

class ApiClient
{
    private const MAX_REQUEST_ATTEMPTS = 10;
    private const ATTEMPTS_DELAY = 10;
    private const REQUEST_TIMEOUT = 60;

    public static ?string $lockId = null;

    private string $endpoint;
    private string $token;
    private Client $client;
    private ResponseBuilder $responseBuilder;

    public function __construct(string $endpoint, string $token)
    {
        $this->client = Guzzle::getInstance([
            'timeout' => self::REQUEST_TIMEOUT,
        ]);
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

        $response = $this->request(fn() => Guzzle::getInstance()->request('POST', $this->endpoint, $options));

        return $this->responseBuilder->build($response);
    }

    private function request(callable $request): ResponseInterface
    {
        $attempt = 1;
        do {
            try {
                /** @var ResponseInterface $response */
                $response = $request();
                if ($response->getStatusCode() === 200) {
                    return $response;
                }
                continue;
            } catch (Throwable $e) {
                sleep(self::ATTEMPTS_DELAY);
            } finally {
                $attempt++;
            }
        } while ($attempt < self::MAX_REQUEST_ATTEMPTS);

        return $request();
    }

}