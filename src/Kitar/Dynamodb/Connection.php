<?php

namespace Kitar\Dynamodb;

use Aws\Sdk as AwsSdk;
use Aws\Sts\StsClient;
use Aws\Credentials\CredentialProvider;
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
        $this->config = $config;

        $this->client = $this->createClient($config);

        $this->tablePrefix = $config['prefix'] ?? '';

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
     * Create a new DynamoDB client.
     * @param array $config
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
            $dynamoConfig['endpoint'] = "https://" . $dynamoConfig['endpoint'];
        }

        if ($key = $config['access_key'] ?? null) {
            $config['key'] = $key;
            unset($config['access_key']);
        }

        if ($key = $config['secret_key'] ?? null) {
            $config['secret'] = $key;
            unset($config['secret_key']);
        }

        // Handle the AssumeRole if one is set
        if ( !empty($config['assume_role']) && preg_match('/^arn:aws:iam::\d{12}:role\/[a-zA-Z0-9+=,.@_-]+$/', $config['assume_role']) ) {
            try {

                // Use IAM credentials if provided, if not, try to use default discovery. e.g. Default EC2 role.
                $credentials = [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ];
                if ( empty($config['key']) && empty($config['secret']) ) {
                    $credentials = CredentialProvider::defaultProvider();
                }

                $stsClient = new StsClient([
                    'version' => '2011-06-15',
                    'region' => $dynamoConfig['region'],
                    'credentials' => $credentials
                ]);

                // Assume the provided role
                $roleCredentials = $stsClient->assumeRole([
                    'RoleArn' => $config['assume_role'],
                    'RoleSessionName' => 'KitarDynamodDBConnection',
                ]);

                $config = [
                    'key' => $roleCredentials['Credentials']['AccessKeyId'],
                    'secret' => $roleCredentials['Credentials']['SecretAccessKey'],
                    'token' => $roleCredentials['Credentials']['SessionToken']  
                ];
            } catch (\Exception $e) {
                throw new \Exception("The assume role failed with message: ".$e->getMessage());
            }
            
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
     * @inheritdoc
     */
    public function disconnect()
    {
        //
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
