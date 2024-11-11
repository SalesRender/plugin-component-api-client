<?php
/**
 * Created for plugin-api-client
 * Datetime: 26.07.2019 15:18
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace SalesRender\Plugin\Components\ApiClient;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use SalesRender\Plugin\Components\Guzzle\Guzzle;
use Softonic\GraphQL\Response;
use Softonic\GraphQL\ResponseBuilder;

class ApiClient
{
    private const REQUEST_TIMEOUT = 60;

    public static ?string $lockId = null;

    private string $endpoint;
    private string $token;
    private Client $client;
    private ResponseBuilder $responseBuilder;
    private int $maxRequestAttempts;
    private int $attemptsDelay;

    public function __construct(string $endpoint, string $token, int $maxRequestAttempts = 10, int $attemptsDelay = 10)
    {
        $this->client = Guzzle::getInstance([
            'timeout' => self::REQUEST_TIMEOUT,
        ]);
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->responseBuilder = new ResponseBuilder();
        $this->maxRequestAttempts = ($maxRequestAttempts <= 0) ? 1 : $maxRequestAttempts;
        $this->attemptsDelay = ($attemptsDelay < 0) ? 0 : $attemptsDelay;
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
                if ($response->getStatusCode() >= 200 and $response->getStatusCode() < 300) {
                    return $response;
                }
                continue;
            } catch (ServerException $e) {
                sleep($this->attemptsDelay);
            } finally {
                $attempt++;
            }
        } while ($attempt < $this->maxRequestAttempts);

        return $request();
    }

}