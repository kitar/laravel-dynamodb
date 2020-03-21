<?php

namespace Kitar\Dynamodb\Tests\Model;

use Aws\Result;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Illuminate\Database\ConnectionResolver;
use Kitar\Dynamodb\Model\KeyMissingException;
use Kitar\Dynamodb\Model\AuthUserProvider;

class AuthUserProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

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
        $connection = m::mock('Kitar\Dynamodb\Connection[clientQuery]', [[]]);

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

        $provider = new AuthUserProvider(new UserA);

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

        $provider = new AuthUserProvider(new UserC);

        $res = $provider->retrieveById('foo@bar.com');

        $this->assertInstanceOf(UserC::class, $res);
    }

    /** @test */
    public function it_cannot_retrieve_by_id_without_default_sort_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider(new UserB);

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

        $provider = new AuthUserProvider(new UserA);

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

        $provider = new AuthUserProvider(new UserA);

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

        $provider = new AuthUserProvider(new UserA);

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

        $provider = new AuthUserProvider(new UserA);

        $res = $provider->retrieveByToken('foo@bar.com', 'invalid_token');

        $this->assertNull($res);
    }

    /** @test */
    public function it_can_update_remember_token()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

        $provider = new AuthUserProvider(new UserA);

        $user = new UserA(['partition' => 'foo@bar.com']);

        $provider->updateRememberToken($user, 'new_token');

        $this->assertEquals('new_token', $user->getRememberToken());
    }

    /** @test */
    public function it_can_retrieve_by_credentials()
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

        $provider = new AuthUserProvider(new UserA);

        $user = $provider->retrieveByCredentials([
            'email' => 'foo@bar.com',
            'password' => 'foo'
        ]);

        $this->assertInstanceOf(UserA::class, $user);
    }
}
