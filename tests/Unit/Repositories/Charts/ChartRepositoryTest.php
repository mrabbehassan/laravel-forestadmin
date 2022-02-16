<?php

namespace ForestAdmin\LaravelForestAdmin\Tests\Unit\Repositories\Charts;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use ForestAdmin\LaravelForestAdmin\Exceptions\ForestException;
use ForestAdmin\LaravelForestAdmin\Repositories\ChartRepository;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Book;
use ForestAdmin\LaravelForestAdmin\Tests\Feature\Models\Mock\CustomModel;
use ForestAdmin\LaravelForestAdmin\Tests\TestCase;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeData;
use ForestAdmin\LaravelForestAdmin\Tests\Utils\FakeSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Mockery as m;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Class ChartRepositoryTest
 *
 * @package Laravel-forestadmin
 * @license GNU https://www.gnu.org/licenses/licenses.html
 * @link    https://github.com/ForestAdmin/laravel-forestadmin
 */
class ChartRepositoryTest extends TestCase
{
    use FakeData;
    use FakeSchema;
    use ProphecyTrait;

    /**
     * @return void
     * @throws Exception
     * @throws SchemaException
     */
    public function testConstruct(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listTableColumns(Argument::any(), Argument::any())
            ->willReturn(['id' => new Column('id', Type::getType('bigint'))]);

        $connection = $this->prophesize(Connection::class);
        $connection->getTablePrefix()
            ->shouldBeCalled()
            ->willReturn('prefix.');
        $connection->getDoctrineSchemaManager()
            ->willReturn($schemaManager->reveal());

        $model = $this->prophesize(CustomModel::class);
        $model
            ->getConnection()
            ->shouldBeCalled()
            ->willReturn($connection->reveal());
        $model
            ->getTable()
            ->shouldBeCalledOnce()
            ->willReturn('dummy_tables');

        $repository = m::mock(ChartRepository::class, [$model->reveal()])
            ->makePartial();

        $this->assertEquals('prefix', $repository->getDatabase());
        $this->assertEquals('dummy_tables', $repository->getTable());
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testGet(): void
    {
        App::shouldReceive('basePath')->andReturn(null);
        File::shouldReceive('get')->andReturn($this->fakeSchema(true));
        $this->getBook()->save();

        $params = '{
            "type": "Objective",
            "collection": "Book",
            "aggregate": "Count"
        }';

        $request = Request::create('/stats/book', 'POST', json_decode($params, true, 512, JSON_THROW_ON_ERROR));
        app()->instance('request', $request);

        $result = [
            'data' => [
                'type'       => 'stats',
                'id'         => '14ceceef-7f97-47ea-b6c9-edcadac232b0',
                'attributes' => [
                    'value' => [
                        'value' => 1,
                    ],
                ],
            ],
        ];

        $repository = m::mock(ChartRepository::class, [Book::first()])
            ->makePartial();
        $repository->shouldReceive('serialize')->with(1)->andReturn($result);
        $get = $repository->get();

        $this->assertIsArray($get);
        $this->assertEquals($result, $get);
    }

    /**
     * @return void
     */
    public function testHandleGroupByField(): void
    {
        $this->getBook()->save();
        $repository = m::mock(ChartRepository::class, [Book::first()])
            ->makePartial();
        $handleField = $repository->handleGroupByField('label');

        $this->assertIsArray($handleField);
        $this->assertEquals(['field' => 'books.label', 'responseField' => 'label'], $handleField);
    }

    /**
     * @return void
     */
    public function testHandleGroupByFieldOnRelation(): void
    {
        $this->getBook()->save();
        $repository = m::mock(ChartRepository::class, [Book::first()])
            ->makePartial();
        $handleField = $repository->handleGroupByField('category:label');
        $result = [
            'relationTable' => 'categories',
            'keys'          => ['books.category_id', 'categories.id'],
            'field'         => 'categories.label',
            'responseField' => 'label',
        ];

        $this->assertIsArray($handleField);
        $this->assertEquals($result, $handleField);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testQuery(): void
    {
        $this->getBook()->save();
        $repository = m::mock(ChartRepository::class, [Book::first()])
            ->makePartial();
        $query = $this->invokeMethod($repository, 'query');

        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * @return void
     */
    public function testFetchFieldsOnRelationBelongsTo(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();

        $fetchFields = $repository->fetchFieldsOnRelation('category', 'label');

        $this->assertIsArray($fetchFields);
        $this->assertEquals(['categories', ['books.category_id', 'categories.id'], 'categories.label'], $fetchFields);
    }

    /**
     * @return void
     */
    public function testFetchFieldsOnRelationHasOne(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();

        $fetchFields = $repository->fetchFieldsOnRelation('advertisement', 'label');

        $this->assertIsArray($fetchFields);
        $this->assertEquals(['advertisements', ['advertisements.book_id', 'books.id'], 'advertisements.label'], $fetchFields);
    }

    /**
     * @return void
     */
    public function testFetchFieldsOnUnknownRelationException(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();

        $this->expectException(ForestException::class);
        $this->expectExceptionMessage("🌳🌳🌳 Unknown relation foo");

        $repository->fetchFieldsOnRelation('foo', 'label');
    }

    /**
     * @return void
     */
    public function testFetchFieldsOnRelationNotAuthorizedExceptino(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();

        $this->expectException(ForestException::class);
        $this->expectExceptionMessage("🌳🌳🌳 Unsupported relation to this chart");

        $repository->fetchFieldsOnRelation('comments', 'body');
    }

    /**
     * @return void
     */
    public function testHandleField(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();
        $handleField = $repository->handleField($book, 'label');

        $this->assertEquals('books.label', $handleField);
    }

    /**
     * @return void
     */
    public function testHandleFieldException(): void
    {
        $this->getBook()->save();
        $book = Book::first();
        $repository = m::mock(ChartRepository::class, [$book])
            ->makePartial();

        $this->expectException(ForestException::class);
        $this->expectExceptionMessage("🌳🌳🌳 The field foo doesn't exist in the table books");

        $repository->handleField($book, 'foo');
    }
}
