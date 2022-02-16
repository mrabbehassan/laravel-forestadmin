<?php

namespace ForestAdmin\LaravelForestAdmin\Tests\Unit\Repositories\Charts;

use Doctrine\DBAL\Exception;
use ForestAdmin\LaravelForestAdmin\Exceptions\ForestException;
use ForestAdmin\LaravelForestAdmin\Repositories\Charts\Simple\Leaderboard;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Book;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Comment;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Range;
use ForestAdmin\LaravelForestAdmin\Tests\TestCase;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeData;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Mockery as m;

/**
 * Class LeaderboardTest
 *
 * @package Laravel-forestadmin
 * @license GNU https://www.gnu.org/licenses/licenses.html
 * @link    https://github.com/ForestAdmin/laravel-forestadmin
 */
class LeaderboardTest extends TestCase
{
    use FakeData;
    use FakeSchema;

    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function testGetCountHasMany(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->makeBooks();

        $params = '{
            "type": "Leaderboard",
            "collection": "book",
            "label_field": "label",
            "relationship_field": "comments",
            "limit": 3,
            "aggregate": "Count"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);
        $repository = new Leaderboard(Book::first());
        $get = $repository->get();
        $result = [
            [
                'key'   => 'test book 10',
                'value' => '10',
            ],
            [
                'key'   => 'test book 9',
                'value' => '9',
            ],
            [
                'key'   => 'test book 8',
                'value' => '8',
            ],
        ];

        $this->assertIsArray($get);
        $this->assertEquals($result, $get);
    }

    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function testGetCountBelongsToMany(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->makeBooks();

        $params = '{
            "type": "Leaderboard",
            "collection": "book",
            "label_field": "label",
            "relationship_field": "comments",
            "limit": 3,
            "aggregate": "Count"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);
        $repository = new Leaderboard(Book::first());
        $get = $repository->get();
        $result = [
            [
                'key'   => 'test book 10',
                'value' => '10',
            ],
            [
                'key'   => 'test book 9',
                'value' => '9',
            ],
            [
                'key'   => 'test book 8',
                'value' => '8',
            ],
        ];

        $this->assertIsArray($get);
        $this->assertEquals($result, $get);
    }

    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function testGetSumHasMany(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->makeBooks();

        $params = '{
            "type": "Leaderboard",
            "collection": "book",
            "label_field": "label",
            "relationship_field": "comments",
            "aggregate_field": "id",
            "limit": 3,
            "aggregate": "Sum"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);
        $repository = new Leaderboard(Book::first());
        $get = $repository->get();

        $result = [
            [
                'key'   => 'test book 10',
                'value' => Book::find(10)->comments()->sum('comments.id'),
            ],
            [
                'key'   => 'test book 9',
                'value' => Book::find(9)->comments()->sum('comments.id'),
            ],
            [
                'key'   => 'test book 8',
                'value' => Book::find(8)->comments()->sum('comments.id'),
            ],
        ];

        $this->assertIsArray($get);
        $this->assertEquals($result, $get);
    }

    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function testGetSumBelongsToMany(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->makeBooks();

        $params = '{
            "type": "Leaderboard",
            "collection": "book",
            "label_field": "label",
            "relationship_field": "ranges",
            "aggregate_field": "id",
            "limit": 3,
            "aggregate": "Sum"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);
        $repository = new Leaderboard(Book::first());
        $get = $repository->get();

        $result = [
            [
                'key'   => 'test book 10',
                'value' => Book::find(10)->ranges()->sum('ranges.id'),
            ],
            [
                'key'   => 'test book 9',
                'value' => Book::find(9)->ranges()->sum('ranges.id'),
            ],
            [
                'key'   => 'test book 8',
                'value' => Book::find(8)->ranges()->sum('ranges.id'),
            ],
        ];

        $this->assertIsArray($get);
        $this->assertEquals($result, $get);
    }

    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function testGetException(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->makeBooks();

        $params = '{
            "type": "Leaderboard",
            "collection": "book",
            "label_field": "label",
            "relationship_field": "category",
            "aggregate_field": "id",
            "limit": 3,
            "aggregate": "Sum"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);
        $repository = new Leaderboard(Book::first());

        $this->expectException(ForestException::class);
        $this->expectExceptionMessage('🌳🌳🌳 Unsupported relation');

        $repository->get();
    }

    /**
     * @return void
     */
    public function testSerialize(): void
    {
        $repository = m::mock(Leaderboard::class, [new Book()])
            ->makePartial();
        $data = ['foo' => 10, 'bar' => 20];
        $serialize = $repository->serialize($data);

        $this->assertIsArray($serialize);
        $this->assertEquals([['key' => 'foo', 'value' => 10], ['key' => 'bar', 'value' => 20]], $serialize);
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