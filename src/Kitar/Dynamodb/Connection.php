<?php

namespace Kitar\Dynamodb;

use Aws\Sdk as AwsSdk;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;

class Connection extends BaseConnection
{
    /**
     * The DynamoDB client.
     * @var \Aws\Dynamodb\DynamoDbClient
     */
    protected $client;

    public function __construct($config)
    {
        $this->client = $this->createClient($config);

        $this->useDefaultPostProcessor();

        $this->useDefaultQueryGrammar();
    }

    /**
     * Begin a fluent query against a database table.
     * @param string $table
     * @param string|null $as
     * @return Query\Builder
     */
    public function table($table, $as = null)
    {
        $query = new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());

        return $query->from($table, $as);
    }

    /**
     * @inheritdoc
     */
    public function getDriverName()
    {
        return 'dynamodb';
    }

    /**
     * Get the DynamoDB Client object.
     * @return \Aws\Dynamodb\DynamoDbClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the DynamoDB client.
     * @param DynamoDbClient $client
     * @return void
     */
    public function setClient(DynamoDbClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new MongoDB client.
     * @param array $config
     * @return \Aws\Dynamodb\DynamoDbClient
     */
    protected function createClient(array $config)
    {
        $dynamoConfig = [
            'region' => $config['region'],
            'version' => 'latest',
        ];

        if (isset($config['key']) && isset($config['secret'])) {
            $dynamoConfig['credentials'] = Arr::only(
                $config, ['key', 'secret', 'token']
            );
        }


        return (new AwsSdk($dynamoConfig))->createDynamoDb();
    }

    /**
     * @inheritdoc
     */
    public function disconnect()
    {
        unset($this->client);

        $this->client = null;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }

    /**
     * Execute query with the DynamoDB Client.
     * @return \Aws\Result
     */
    public function clientQuery($params)
    {
        return $this->client->query($params);
    }

    /**
     * Dynamically pass methods to the connection.
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->client, $method], $parameters);
    }
}
