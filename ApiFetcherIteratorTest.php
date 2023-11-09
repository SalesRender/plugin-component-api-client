<?php
/**
 * Created for plugin-component-api-client
 * Date: 31.05.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Components\ApiClient;

use Mockery;
use PHPUnit\Framework\TestCase;
use Softonic\GraphQL\Response;

class ApiFetcherIteratorTest extends TestCase
{

    private array $fields;

    private ApiClient $client;

    private ApiFilterSortPaginate $fsp;

    private int $beforeCounter;
    private array $afterArray = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->beforeCounter = 0;
        $this->afterArray = [];
        $this->client = Mockery::mock(ApiClient::class);
        $this->client->shouldReceive('query')->andReturn(
            new Response(['path' => ['orders' => [['id' => 1], ['id' => 2], ['id' => 3]]]]),
            new Response(['path' => ['orders' => [['id' => 4], ['id' => 5], ['id' => 6]]]]),
            new Response(['path' => ['orders' => [['id' => 7], ['id' => 8], ['id' => 9]]]]),
            new Response(['path' => ['orders' => []]]),
        );

        $this->fields = ['orders' => ['id']];
        $this->fsp = new ApiFilterSortPaginate([], null, 3);
    }

    public function noLimitDataProvider(): array
    {
        return [
            [null],
            [0],
            [-1],
        ];
    }

    /**
     * @dataProvider noLimitDataProvider
     * @param int|null $limit
     */
    public function testIterate(?int $limit): void
    {
        $result = $this->iterate($this->createIterator($limit));
        $this->assertSame([
            ['id' => 1, 'before' => 1, 'after' => 0],
            ['id' => 2, 'before' => 1, 'after' => 0],
            ['id' => 3, 'before' => 1, 'after' => 0],
            ['id' => 4, 'before' => 2, 'after' => 1],
            ['id' => 5, 'before' => 2, 'after' => 1],
            ['id' => 6, 'before' => 2, 'after' => 1],
            ['id' => 7, 'before' => 3, 'after' => 2],
            ['id' => 8, 'before' => 3, 'after' => 2],
            ['id' => 9, 'before' => 3, 'after' => 2],
        ], $result);

        $this->assertSame(4, $this->beforeCounter);
        $this->assertSame([
            [['id' => 1], ['id' => 2], ['id' => 3]],
            [['id' => 4], ['id' => 5], ['id' => 6]],
            [['id' => 7], ['id' => 8], ['id' => 9]],
        ], $this->afterArray);
    }

    public function testIterateWithMiddleLimit(): void
    {
        $result = $this->iterate($this->createIterator(5));
        $this->assertSame([
            ['id' => 1, 'before' => 1, 'after' => 0],
            ['id' => 2, 'before' => 1, 'after' => 0],
            ['id' => 3, 'before' => 1, 'after' => 0],
            ['id' => 4, 'before' => 2, 'after' => 1],
            ['id' => 5, 'before' => 2, 'after' => 1],
        ], $result);

        $this->assertSame(2, $this->beforeCounter);
        $this->assertSame([
            [['id' => 1], ['id' => 2], ['id' => 3]],
            [['id' => 4], ['id' => 5]],
        ], $this->afterArray);
    }

    public function testIterateWithEdgeLimit(): void
    {
        $result = $this->iterate($this->createIterator(6));
        $this->assertSame([
            ['id' => 1, 'before' => 1, 'after' => 0],
            ['id' => 2, 'before' => 1, 'after' => 0],
            ['id' => 3, 'before' => 1, 'after' => 0],
            ['id' => 4, 'before' => 2, 'after' => 1],
            ['id' => 5, 'before' => 2, 'after' => 1],
            ['id' => 6, 'before' => 2, 'after' => 1],
        ], $result);

        $this->assertSame(2, $this->beforeCounter);
        $this->assertSame([
            [['id' => 1], ['id' => 2], ['id' => 3]],
            [['id' => 4], ['id' => 5], ['id' => 6]],
        ], $this->afterArray);
    }

    private function iterate(ApiFetcherIterator $iterator): array
    {
        $result = [];
        foreach ($iterator as $data) {
            $result[] = [
                'id' => $data['id'],
                'before' => $this->beforeCounter,
                'after' => count($this->afterArray),
            ];
        }
        return $result;
    }

    private function createIterator(int $limit = null): ApiFetcherIterator
    {
        $iterator = new class($this->fields, $this->client, $this->fsp, true, $limit) extends ApiFetcherIterator {

            protected function getQuery(array $fields): string
            {
                return 'query';
            }

            protected function getQueryPath(): string
            {
                return 'path';
            }

            protected function getIdentity(array $array): string
            {
                return $array['id'];
            }
        };

        $iterator->setOnBeforeBatch(function () {
            $this->beforeCounter++;
        });

        $iterator->setOnAfterBatch(function (array $batch) {
            $this->afterArray[] = $batch;
        });

        return $iterator;
    }

}
