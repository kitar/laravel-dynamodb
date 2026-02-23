<?php

namespace Kitar\Dynamodb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Sdk as AwsSdk;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;

class Connection extends BaseConnection
{
    /**
     * The DynamoDB client.
     *
     * @var \Aws\Dynamodb\DynamoDbClient
     */
    protected $client;

    /**
     * Bypass the parent constructor because DynamoDB does not use PDO.
     *
     * @param  array  $config
     */
    public function __construct($config)
    {
        $this->config = $config;

        $this->client = $this->createClient($config);

        $this->tablePrefix = $config['prefix'] ?? '';

        $this->useDefaultPostProcessor();

        $this->useDefaultQueryGrammar();
    }

    /**
     * {@inheritdoc}
     */
    public function table($table, $as = null)
    {
        $table = $table instanceof \BackedEnum ? $table->value
            : ($table instanceof \UnitEnum ? $table->name : $table);

        return $this->query()->from($table, $as);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'dynamodb';
    }

    /**
     * Get the DynamoDB Client object.
     *
     * @return \Aws\Dynamodb\DynamoDbClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the DynamoDB client.
     *
     * @return void
     */
    public function setClient(DynamoDbClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new DynamoDB client.
     *
     * @return \Aws\Dynamodb\DynamoDbClient
     */
    protected function createClient(array $config)
    {
        $dynamoConfig = [
            'region' => $config['region'] ?? 'us-east-1',
            'version' => $config['version'] ?? 'latest',
            'endpoint' => $config['endpoint'] ?? null,
        ];

        if (! empty($dynamoConfig['endpoint']) && preg_match('#^https?://#i', $dynamoConfig['endpoint']) === 0) {
            $dynamoConfig['endpoint'] = 'https://'.$dynamoConfig['endpoint'];
        }

        if ($key = $config['access_key'] ?? null) {
            $config['key'] = $key;
            unset($config['access_key']);
        }

        if ($key = $config['secret_key'] ?? null) {
            $config['secret'] = $key;
            unset($config['secret_key']);
        }

        if (isset($config['key']) && isset($config['secret'])) {
            $dynamoConfig['credentials'] = Arr::only(
                $config,
                ['key', 'secret', 'token']
            );
        }

        return (new AwsSdk($dynamoConfig))->createDynamoDb();
    }

    /**
     * No-op. DynamoDB is HTTP-based and has no persistent connection.
     *
     * @return void
     */
    public function disconnect()
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar($this);
    }

    /**
     * Execute query with the DynamoDB Client.
     *
     * @return \Aws\Result
     */
    public function clientQuery($params)
    {
        return $this->client->query($params);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->client, $method], $parameters);
    }
}
