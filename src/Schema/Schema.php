<?php

namespace ForestAdmin\LaravelForestAdmin\Schema;

use Composer\Autoload\ClassMapGenerator;
use Doctrine\DBAL\Exception;
use ForestAdmin\LaravelForestAdmin\Services\ForestApiRequester;
use ForestAdmin\LaravelForestAdmin\Services\SmartFeatures\SmartCollection;
use ForestAdmin\LaravelForestAdmin\Utils\Database;
use ForestAdmin\LaravelForestAdmin\Utils\Traits\FormatGuzzle;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class Schema
 *
 * @package Laravel-forestadmin
 * @license GNU https://www.gnu.org/licenses/licenses.html
 * @link    https://github.com/ForestAdmin/laravel-forestadmin
 */
class Schema
{
    use FormatGuzzle;

    public const LIANA_NAME = 'laravel-forestadmin';

    public const LIANA_VERSION = '1.0.0-beta.12';

    /**
     * @var string
     */
    protected string $directory;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var ForestApiRequester
     */
    private ForestApiRequester $forestApi;

    /**
     * @var ConsoleOutput
     */
    private ConsoleOutput $console;

    /**
     * @param Config             $config
     * @param ForestApiRequester $forestApi
     * @param ConsoleOutput      $console
     */
    public function __construct(Config $config, ForestApiRequester $forestApi, ConsoleOutput $console)
    {
        $this->config = $config;
        $this->directory = App::basePath($config->get('forest.models_directory'));
        $this->forestApi = $forestApi;
        $this->console = $console;
    }

    /**
     * @return void
     * @throws Exception
     * @throws GuzzleException
     * @throws BindingResolutionException
     */
    public function sendApiMap(): void
    {
        $response = $this->forestApi->post(
            '/forest/apimaps',
            [],
            $this->serialize()
        );

        $this->console->write('🌳🌳🌳 ');

        if (in_array($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_ACCEPTED, Response::HTTP_NO_CONTENT], true)) {
            $this->console->writeln('<info>Apimap Received<info>');
        } else {
            $this->console->writeln('<error>Cannot send the apimap to Forest. Are you online?</error>');
        }
    }

    /**
     * @return array
     * @throws BindingResolutionException
     * @throws Exception
     * @throws \JsonException
     */
    private function generate(): array
    {
        $files = $this->fetchFiles();
        $schema = new Collection($this->metadata());
        $collections = [];

        foreach ($files as $file) {
            if (class_exists($file)) {
                $class = (new \ReflectionClass($file));
                if ($class->isSubclassOf(Model::class) && $class->isInstantiable()) {
                    $model = app()->make($file);
                    $forestModel = new ForestModel($model);
                    $collections[] = $forestModel->serialize();
                } elseif ($class->isSubclassOf(SmartCollection::class) && $class->isInstantiable()) {
                    $smartCollection = app()->make($file);
                    $collections[] = $smartCollection->serialize();
                }
            }
        }
        $schema->put('collections', $collections);
        File::put($this->config->get('forest.json_file_path'), json_encode($schema, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $schema->toArray();
    }

    /**
     * @return array
     * @throws BindingResolutionException
     * @throws Exception
     * @throws \JsonException
     */
    private function serialize(): array
    {
        $schema = $this->generate();
        $data = [];
        $included = [];

        foreach ($schema['collections'] as $collection) {
            $collectionActions = $collection['actions'];
            $collectionSegments = $collection['segments'];
            unset($collection['actions'], $collection['segments']);

            $included[] = $this->getSmartFeaturesByCollection('actions', $collectionActions, true);
            $included[] = $this->getSmartFeaturesByCollection('segments', $collectionSegments, true);

            $data[] = [
                'id'            => $collection['name'],
                'type'          => 'collections',
                'attributes'    => $collection,
                'relationships' => [
                    'actions'  => [
                        'data' => $this->getSmartFeaturesByCollection('actions', $collectionActions)
                    ],
                    'segments' => [
                        'data' => $this->getSmartFeaturesByCollection('segments', $collectionSegments)
                    ]
                ]
            ];
        }

        return [
            'data'     => $data,
            'included' => array_merge(...$included),
            'meta'     => $schema['meta'],
        ];
    }

    /**
     * @param string $type
     * @param array  $data
     * @param bool   $withAttributes
     * @return array
     */
    private function getSmartFeaturesByCollection(string $type, array $data, bool $withAttributes = false): array
    {
        $smartFeatures = [];

        foreach ($data as $value) {
            $smartFeature = [
                'id'   => $value['id'],
                'type' => $type,
            ];
            if ($withAttributes) {
                $smartFeature['attributes'] = $value;
            }
            $smartFeatures[] = $smartFeature;
        }

        return $smartFeatures;
    }


    /**
     * Fetch all files in the model directory
     * @return Collection
     */
    private function fetchFiles(): Collection
    {
        $files = new Collection();

        foreach (glob($this->directory, GLOB_ONLYDIR) as $dir) {
            if (file_exists($dir)) {
                $fileClass = ClassMapGenerator::createMap($dir);
                foreach (array_keys($fileClass) as $file) {
                    $files->push($file);
                }
            }
        }

        return $files;
    }

    /**
     * @return array
     */
    private function metadata(): array
    {
        return [
            'meta' => [
                'liana'         => self::LIANA_NAME,
                'liana_version' => self::LIANA_VERSION,
                'stack'         => [
                    'database_type' => Database::getSource($this->config->get('database.default')),
                    'orm_version'   => app()->version(),
                ],
            ],
        ];
    }
}
