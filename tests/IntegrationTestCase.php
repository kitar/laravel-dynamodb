<?php

namespace Kitar\Dynamodb\Tests;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Kitar\Dynamodb\DynamodbServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class IntegrationTestCase extends OrchestraTestCase
{
    protected static bool $dynamoDbAvailable = false;

    protected static bool $checkedAvailability = false;

    protected DynamoDbClient $dynamoDbClient;

    /**
     * Table definitions to create in DynamoDB Local.
     * Can be overridden in subclasses.
     */
    protected static array $tables = [
        'SimpleUser' => [
            'KeySchema' => [
                ['AttributeName' => 'partition', 'KeyType' => 'HASH'],
            ],
            'AttributeDefinitions' => [
                ['AttributeName' => 'partition', 'AttributeType' => 'S'],
            ],
        ],
        'CompositeUser' => [
            'KeySchema' => [
                ['AttributeName' => 'partition', 'KeyType' => 'HASH'],
                ['AttributeName' => 'sort', 'KeyType' => 'RANGE'],
            ],
            'AttributeDefinitions' => [
                ['AttributeName' => 'partition', 'AttributeType' => 'S'],
                ['AttributeName' => 'sort', 'AttributeType' => 'S'],
            ],
        ],
        'Thread' => [
            'KeySchema' => [
                ['AttributeName' => 'ForumName', 'KeyType' => 'HASH'],
                ['AttributeName' => 'Subject', 'KeyType' => 'RANGE'],
            ],
            'AttributeDefinitions' => [
                ['AttributeName' => 'ForumName', 'AttributeType' => 'S'],
                ['AttributeName' => 'Subject', 'AttributeType' => 'S'],
            ],
        ],
    ];

    protected function getPackageProviders($app): array
    {
        return [
            DynamodbServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $endpoint = env('DYNAMODB_LOCAL_ENDPOINT', 'http://localhost:8000');

        $app['config']->set('database.default', 'dynamodb');
        $app['config']->set('database.connections.dynamodb', [
            'driver' => 'dynamodb',
            'region' => 'us-east-1',
            'endpoint' => $endpoint,
            'access_key' => 'dummy',
            'secret_key' => 'dummy',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$checkedAvailability) {
            static::$dynamoDbAvailable = $this->checkDynamoDbAvailable();
            static::$checkedAvailability = true;
        }

        if (! static::$dynamoDbAvailable) {
            $this->markTestSkipped(
                'DynamoDB Local is not available. Start it with: docker compose up -d'
            );
        }

        $this->dynamoDbClient = $this->app['db']->connection('dynamodb')->getClient();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        if (static::$dynamoDbAvailable) {
            $this->deleteTables();
        }

        parent::tearDown();
    }

    protected function checkDynamoDbAvailable(): bool
    {
        try {
            $this->app['db']->connection('dynamodb')->getClient()->listTables();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createTables(): void
    {
        foreach (static::$tables as $tableName => $schema) {
            try {
                $this->dynamoDbClient->createTable(array_merge([
                    'TableName' => $tableName,
                    'BillingMode' => 'PAY_PER_REQUEST',
                ], $schema));
            } catch (DynamoDbException $e) {
                if ($e->getAwsErrorCode() !== 'ResourceInUseException') {
                    throw $e;
                }
                // Table already exists, clear its data
                $this->truncateTable($tableName, $schema);
            }
        }
    }

    protected function deleteTables(): void
    {
        foreach (array_keys(static::$tables) as $tableName) {
            try {
                $this->dynamoDbClient->deleteTable(['TableName' => $tableName]);
            } catch (DynamoDbException $e) {
                // Ignore if table does not exist
            }
        }
    }

    protected function truncateTable(string $tableName, array $schema): void
    {
        $keyAttributes = array_map(
            fn ($key) => $key['AttributeName'],
            $schema['KeySchema']
        );

        $result = $this->dynamoDbClient->scan(['TableName' => $tableName]);

        foreach ($result['Items'] as $item) {
            $key = array_intersect_key($item, array_flip($keyAttributes));
            $this->dynamoDbClient->deleteItem([
                'TableName' => $tableName,
                'Key' => $key,
            ]);
        }
    }
}
