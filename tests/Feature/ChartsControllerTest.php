<?php

namespace ForestAdmin\LaravelForestAdmin\Tests\Feature;

use ForestAdmin\LaravelForestAdmin\Auth\OAuth2\ForestResourceOwner;
use ForestAdmin\LaravelForestAdmin\Exports\CollectionExport;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Book;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Comment;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Range;
use ForestAdmin\LaravelForestAdmin\Tests\TestCase;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeData;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeSchema;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\MockForestUserFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ChartsControllerTest
 *
 * @package  Laravel-forestadmin
 * @license  GNU https://www.gnu.org/licences/licences.html
 * @link     https://github.com/ForestAdmin/laravel-forestadmin
 */
class ChartsControllerTest extends TestCase
{
    use FakeData;
    use FakeSchema;
    use MockForestUserFactory;

    /**
     * @var ForestResourceOwner
     */
    private ForestResourceOwner $forestResourceOwner;

    /**
     * @param Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('forest.models_namespace', 'ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\\');
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->forestResourceOwner = new ForestResourceOwner(
            [
                'type'                              => 'users',
                'id'                                => '1',
                'first_name'                        => 'John',
                'last_name'                         => 'Doe',
                'email'                             => 'jdoe@forestadmin.com',
                'teams'                             => [
                    0 => 'Operations',
                ],
                'tags'                              => [
                    0 => [
                        'key'   => 'demo',
                        'value' => '1234',
                    ],
                ],
                'two_factor_authentication_enabled' => false,
                'two_factor_authentication_active'  => false,
            ],
            1234
        );
        $this->withHeader('Authorization', 'Bearer ' . $this->forestResourceOwner->makeJwt());

        $this->mockForestUserFactory();
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryTypeException(): void
    {
        $data = $this->getTestingDataLiveQueries('Value');
        //--- Override type for testing throw exception ---//
        $data['payloadQuery']['type'] = 'Foo';
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $call->baseResponse->getStatusCode());
        $this->assertEquals('🌳🌳🌳 The chart\'s type is not recognized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryPermissionDenied(): void
    {
        $data = $this->getTestingDataLiveQueries('Value');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryValue(): void
    {
        $data = $this->getTestingDataLiveQueries('Value');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $permission = [
            'stats' => [
                'queries' => [$data['payloadQuery']['query']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryObjective(): void
    {
        $data = $this->getTestingDataLiveQueries('Objective');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $permission = [
            'stats' => [
                'queries' => [$data['payloadQuery']['query']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryPie(): void
    {
        $data = $this->getTestingDataLiveQueries('Pie');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $permission = [
            'stats' => [
                'queries' => [$data['payloadQuery']['query']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryLine(): void
    {
        $data = $this->getTestingDataLiveQueries('Line');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $permission = [
            'stats' => [
                'queries' => [$data['payloadQuery']['query']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testLiveQueryLeaderboard(): void
    {
        $data = $this->getTestingDataLiveQueries('Leaderboard');
        DB::shouldReceive('select')->set('query', $data['payloadQuery'])->andReturn($data['queryResult']);
        $permission = [
            'stats' => [
                'queries' => [$data['payloadQuery']['query']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats', $data['payloadQuery']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexValue(): void
    {
        $this->getBook()->save();
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Value');
        $permission = [
            'stats' => [
                'values' => [$data['permission']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexValuePermissionDenied(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Value');
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexObjective(): void
    {
        $this->getBook()->save();
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Objective');
        //-- unset this key because, it's only present when is a liveQuery chart --//
        unset($data['expected']['objective']);
        $permission = [
            'stats' => [
                'objectives' => [$data['permission']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexObjectivePermissionDenied(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Objective');
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexPie(): void
    {
        $this->getBook()->save();
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Pie');
        $permission = [
            'stats' => [
                'pies' => [$data['permission']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexPiePermissionDenied(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Pie');
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexLine(): void
    {
        $this->getBook()->save();
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Line');
        $permission = [
            'stats' => [
                'lines' => [$data['permission']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexLinePermissionDenied(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Line');
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexLeaderboard(): void
    {
        $this->makeBooks();
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Leaderboard');
        $permission = [
            'stats' => [
                'leaderboards' => [$data['permission']],
            ]
        ];
        $this->mockForestUserFactory(true, $permission);
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertChartResponse($data['expected'], $response);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testIndexLeaderboardPermissionDenied(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $data = $this->getTestingDataLiveQueries('Leaderboard');
        $call = $this->postJson('/forest/stats/book', $data['payload']);
        $response = json_decode($call->baseResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(JsonResponse::class, $call->baseResponse);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $call->baseResponse->getStatusCode());
        $this->assertEquals('This action is unauthorized.', $response['message']);
    }

    /**
     * @param array $expected
     * @param array $response
     * @return void
     */
    public function assertChartResponse(array $expected, array $response): void
    {
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('type', $response['data']);
        $this->assertEquals('stats', $response['data']['type']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertArrayHasKey('attributes', $response['data']);
        $this->assertArrayHasKey('value', $response['data']['attributes']);
        $this->assertEquals($expected, $response['data']['attributes']['value']);
    }

    /**
     * @param string $type
     * @return array[]
     */
    public function getTestingDataLiveQueries(string $type): array
    {
        $testingData = [
            'Value'       => [
                'payloadQuery'     => [
                    'type'  => 'Value',
                    'query' => "select count('*') as value from books where books.label = 'foo'",
                ],
                'payload' => [
                    'type'            => 'Value',
                    'collection'      => 'book',
                    'aggregate'       => 'Count',
                    'filters'         => "{\"field\":\"label\",\"operator\":\"equal\",\"value\":\"foo\"}"
                ],
                'permission' => [
                    'type'               => 'Value',
                    'sourceCollectionId' => 'book',
                    'aggregator'         => 'Count',
                    'filter'             => "{\"field\":\"label\",\"operator\":\"equal\",\"value\":\"foo\"}"
                ],
                'queryResult' => [
                    (object) ['value' => 1],
                ],
                'expected'    => [
                    'countCurrent'  => 1,
                    'countPrevious' => null,
                ],
            ],
            'Objective'   => [
                'payloadQuery'     => [
                    'type'  => 'Objective',
                    'query' => "select count(*) as value, 10 as objective from books",
                ],
                'payload' => [
                    'type'            => 'Objective',
                    'collection'      => 'book',
                    'aggregate'       => 'Count',
                ],
                'permission' => [
                    'type'               => 'Objective',
                    'sourceCollectionId' => 'book',
                    'aggregator'         => 'Count',
                ],
                'queryResult' => [
                    (object) ['value' => 1, 'objective' => 10],
                ],
                'expected'    => [
                    'value'     => 1,
                    'objective' => 10,
                ],
            ],
            'Pie'         => [
                'payloadQuery'     => [
                    'type'  => 'Pie',
                    'query' => "select COUNT(books.label) as value, books.label as key from books group by books.label",
                ],
                'payload' => [
                    'type'            => 'Pie',
                    'collection'      => 'book',
                    'aggregate'       => 'Count',
                    'group_by_field'  => 'label',
                    'filters'         => "{\"field\":\"label\",\"operator\":\"equal\",\"value\":\"foo\"}",
                ],
                'permission' => [
                    'type'               => 'Pie',
                    'sourceCollectionId' => 'book',
                    'aggregator'         => 'Count',
                    'groupByFieldName'   => 'label',
                    'filter'             => "{\"field\":\"label\",\"operator\":\"equal\",\"value\":\"foo\"}",
                ],
                'queryResult' => [
                    (object) ['value' => 1, 'key' => 'foo'],
                ],
                'expected'    => [
                    ['value' => 1, 'key' => 'foo'],
                ],
            ],
            'Line'        => [
                'payloadQuery'     => [
                    'type'  => 'Line',
                    'query' => "select count(*) as value, to_char(created_at, 'dd/mm/yyyy') as key from books group by key",
                ],
                'payload' => [
                    'aggregate'           => 'Count',
                    'collection'          => 'book',
                    'group_by_date_field' => 'created_at',
                    'time_range'          => 'Day',
                    'type'                => 'Line',
                ],
                'permission' => [
                    'type'               => 'Line',
                    'sourceCollectionId' => 'book',
                    'aggregator'         => 'Count',
                    'groupByFieldName'   => 'created_at',
                    'timeRange'          => 'Day',
                ],
                'queryResult' => [
                    (object) ['value' => 1, 'key' => Carbon::now()->format('d/m/Y')],
                ],
                'expected'    => [
                    [
                        'label'  => Carbon::now()->format('d/m/Y'),
                        'values' => ['value' => 1],
                    ],
                ],
            ],
            'Leaderboard' => [
                'payloadQuery'     => [
                    'type'  => 'Leaderboard',
                    'query' => "select books.label as key, count(c.id) as value from books left join comments c on books.id = c.book_id GROUP BY books.label LIMIT 3",
                ],
                'payload' => [
                    'type'               => 'Leaderboard',
                    'collection'         => 'book',
                    'label_field'        => 'label',
                    'relationship_field' => 'comments',
                    'aggregate_field'    => 'id',
                    'limit'              => 3,
                    'aggregate'          => 'Count',
                ],
                'permission' => [
                    'type'                  => 'Leaderboard',
                    'sourceCollectionId'    => 'book',
                    'labelFieldName'        => 'label',
                    'relationshipFieldName' => 'comments',
                    'aggregateFieldName'    => 'id',
                    'limit'                 => 3,
                    'aggregator'            => 'Count',
                ],
                'queryResult' => [
                    (object) ['value' => 10, 'key' => 'test book 10'],
                    (object) ['value' => 9, 'key' => 'test book 9'],
                    (object) ['value' => 8, 'key' => 'test book 8'],
                ],
                'expected'    => [
                    ['value' => 10, 'key' => 'test book 10'],
                    ['value' => 9, 'key' => 'test book 9'],
                    ['value' => 8, 'key' => 'test book 8'],
                ],
            ],
        ];

        return $testingData[$type];
    }

    /**
     * @return void
     */
    public function makeBooks(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $book = Book::create(
                [
                    'label'        => 'test book ' . $i + 1,
                    'comment'      => '',
                    'difficulty'   => 'easy',
                    'amount'       => 1000,
                    'options'      => [],
                    'category_id'  => 1,
                    'published_at' => Carbon::today()->subDays(rand(0, 1)),
                ]
            );

            for ($j = 0; $j < $i + 1; $j++) {
                Comment::create(
                    [
                        'body'    => 'Test comment',
                        'user_id' => 1,
                        'book_id' => $book->id,
                    ]
                );
            }

            for ($j = 0; $j < $i + 1; $j++) {
                Range::create(
                    [
                        'label' => 'Test range',
                    ]
                )->books()->save($book);
            }
        }
    }
}