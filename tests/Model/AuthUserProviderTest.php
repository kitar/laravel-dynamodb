<?php

namespace Attla\Dynamodb\Tests\Model;

use Aws\Result;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Hashing\BcryptHasher;
use Attla\Dynamodb\Exceptions\KeyMissingException;
use Attla\Dynamodb\Model\AuthUserProvider;

class AuthUserProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected $hasher;

    protected function setUp() :void
    {
        $this->hasher = new BcryptHasher;
    }

    protected function tearDown() :void
    {
        m::close();
    }

    protected function setConnectionResolver($connection)
    {
        $connectionResolver = new ConnectionResolver;
        $connectionResolver->addConnection('dynamodb', $connection);
        $connectionResolver->setDefaultConnection('dynamodb');
        UserA::setConnectionResolver($connectionResolver);
        UserB::setConnectionResolver($connectionResolver);
        UserC::setConnectionResolver($connectionResolver);
    }

    protected function newConnectionMock()
    {
        $connection = m::mock('Attla\Dynamodb\Connection[clientQuery]', [[]]);

        return $connection;
    }

    protected function sampleAwsResult()
    {
        return new Result([
            'Item' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ],
                'password' => [
                    'S' => 'foo'
                ],
                'remember_token' => [
                    'S' => 'valid_token'
                ],
                'api_token' => [
                    'S' => 'valid_api_token'
                ]
            ],
            '@metadata' => [
                'statuscode' => 200
            ]
        ]);
    }

    protected function sampleAwsResultMultiple()
    {
        return new Result([
            'Items' => [
                [
                    'partition' => [
                        'S' => 'foo@bar.com'
                    ],
                    'password' => [
                        'S' => 'foo'
                    ],
                    'remember_token' => [
                        'S' => 'valid_token'
                    ],
                    'api_token' => [
                        'S' => 'valid_api_token'
                    ]
                ]
            ],
            '@metadata' => [
                'statuscode' => 200
            ]
        ]);
    }

    protected function sampleAwsResultEmpty()
    {
        return new Result([
            '@metadata' => [
                'statuscode' => 200
            ]
        ]);
    }

    /** @test */
    public function it_can_retrieve_by_id()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $res = $provider->retrieveById('foo@bar.com');

        $this->assertInstanceOf(UserA::class, $res);
    }

    /** @test */
    public function it_can_retrieve_by_id_with_default_sort_key()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ],
                'sort' => [
                    'S' => 'sort_default'
                ]
            ]
        ])->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserC::class);

        $res = $provider->retrieveById('foo@bar.com');

        $this->assertInstanceOf(UserC::class, $res);
    }

    /** @test */
    public function it_cannot_retrieve_by_id_without_default_sort_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserB::class);

        $this->expectException(KeyMissingException::class);

        $provider->retrieveById('foo@bar.com');
    }

    /** @test */
    public function it_cannot_retrieve_by_id_if_not_exists()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResultEmpty());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $res = $provider->retrieveById('foo@bar.com');

        $this->assertNull($res);
    }

    /** @test */
    public function it_can_retrieve_by_token()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $res = $provider->retrieveByToken('foo@bar.com', 'valid_token');

        $this->assertInstanceOf(UserA::class, $res);
    }

    /** @test */
    public function it_cannot_retrieve_by_token_if_not_exists()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResultEmpty());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $res = $provider->retrieveByToken('foo@bar.com', 'valid_token');

        $this->assertNull($res);
    }

    /** @test */
    public function it_cannot_retrieve_by_token_with_invalid_token()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $res = $provider->retrieveByToken('foo@bar.com', 'invalid_token');

        $this->assertNull($res);
    }

    /** @test */
    public function it_can_update_remember_token()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ],
            'UpdateExpression' => 'set #1 = :1',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'remember_token'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'new_token'
                ]
            ]
        ])->andReturn($this->sampleAwsResultEmpty());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $user = (new UserA)->newFromBuilder(['partition' => 'foo@bar.com']);

        $provider->updateRememberToken($user, 'new_token');

        $this->assertEquals('new_token', $user->getRememberToken());
    }

    /** @test */
    public function it_can_retrieve_by_credentials_with_basic_credentials()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo@bar.com'
                ]
            ]
        ])->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $user = $provider->retrieveByCredentials([
            'partition' => 'foo@bar.com',
            'password' => 'foo'
        ]);

        $this->assertInstanceOf(UserA::class, $user);
    }

    /** @test */
    public function it_can_retrieve_by_credentials_with_api_token()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('clientQuery')->with([
            'TableName' => 'User',
            'IndexName' => 'api_token-index',
            'KeyConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'api_token'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'valid_api_token'
                ]
            ]
        ])->andReturn($this->sampleAwsResultMultiple());
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider($this->hasher, UserA::class, 'api_token', 'api_token-index');

        $user = $provider->retrieveByCredentials([
            'api_token' => 'valid_api_token',
        ]);

        $this->assertInstanceOf(UserA::class, $user);
    }

    /** @test */
    public function it_cannot_retrieve_by_credentials_with_multiple_conditions()
    {
        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $result = $provider->retrieveByCredentials([
            'partition' => 'foo@bar.com',
            'foo' => 'bar'
        ]);

        $this->assertNull($result);
    }

    /** @test */
    public function it_cannot_retrieve_by_credentials_if_key_is_not_supported()
    {
        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $result = $provider->retrieveByCredentials([
            'foo' => 'bar'
        ]);

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_validate_credentials()
    {
        $user = new UserA([
            'partition' => 'foo@bar.com',
            'password' => '$2y$10$ouGGlM0C/YKgk8MbQHxVHOblxztk/PlXZbKw7w2wfA8FlXsB0Po9G'
        ]);

        $provider = new AuthUserProvider($this->hasher, UserA::class);

        $success = $provider->validateCredentials($user, [
            'partition' => 'foo@bar.com',
            'password'=> 'foo'
        ]);

        $fail = $provider->validateCredentials($user, [
            'partition' => 'foo@bar.com',
            'password' => 'bar'
        ]);

        $this->assertTrue($success);
        $this->assertFalse($fail);
    }

    /** @test */
    public function it_can_rehash_password_if_required()
    {
        if (! method_exists(\Illuminate\Contracts\Auth\UserProvider::class, 'rehashPasswordIfRequired')) {
            $this->markTestSkipped('Password rehash is not supported in this version.');
        }

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->andReturn($this->sampleAwsResult());
        $this->setConnectionResolver($connection);

        $originalHash = '$2y$10$ouGGlM0C/YKgk8MbQHxVHOblxztk/PlXZbKw7w2wfA8FlXsB0Po9G';
        $user = new UserA([
            'partition' => 'foo@bar.com',
            'password' => $originalHash,
        ]);
        $provider = new AuthUserProvider($this->hasher, UserA::class);
        $provider->rehashPasswordIfRequired($user, ['password' => 'foo'], true);
        $this->assertNotSame($originalHash, $user->password);
        $this->assertTrue($this->hasher->check('foo', $user->password));

        $rehashedHash = $user->password;
        $provider->rehashPasswordIfRequired($user, ['password' => 'foo']);
        $this->assertSame($rehashedHash, $user->password);
    }
}
