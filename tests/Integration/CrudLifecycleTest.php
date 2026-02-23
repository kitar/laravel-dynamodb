<?php

namespace Kitar\Dynamodb\Tests\Integration;

use Kitar\Dynamodb\Helpers\Collection;
use Kitar\Dynamodb\Tests\Integration\Model\CompositeUser;
use Kitar\Dynamodb\Tests\Integration\Model\SimpleUser;
use Kitar\Dynamodb\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class CrudLifecycleTest extends IntegrationTestCase
{
    #[Test]
    public function it_can_create_and_find_item_with_partition_key_only()
    {
        $user = SimpleUser::create(['partition' => 'user-1', 'name' => 'Alice']);

        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);

        $found = SimpleUser::find('user-1');

        $this->assertInstanceOf(SimpleUser::class, $found);
        $this->assertEquals('Alice', $found->name);
        $this->assertTrue($found->exists);
    }

    #[Test]
    public function it_can_create_and_find_item_with_composite_key()
    {
        CompositeUser::create(['partition' => 'p1', 'sort' => 's1', 'name' => 'Bob']);

        $found = CompositeUser::find(['partition' => 'p1', 'sort' => 's1']);

        $this->assertInstanceOf(CompositeUser::class, $found);
        $this->assertEquals('Bob', $found->name);
    }

    #[Test]
    public function it_returns_null_when_item_not_found()
    {
        $found = SimpleUser::find('nonexistent');

        $this->assertNull($found);
    }

    #[Test]
    public function it_can_update_existing_item()
    {
        SimpleUser::create(['partition' => 'user-1', 'name' => 'Alice']);

        $user = SimpleUser::find('user-1');
        $user->name = 'Alice Updated';
        $user->save();

        $found = SimpleUser::find('user-1');
        $this->assertEquals('Alice Updated', $found->name);
    }

    #[Test]
    public function it_can_delete_existing_item()
    {
        SimpleUser::create(['partition' => 'user-1', 'name' => 'Alice']);

        $user = SimpleUser::find('user-1');
        $user->delete();

        $this->assertNull(SimpleUser::find('user-1'));
        $this->assertFalse($user->exists);
    }

    #[Test]
    public function it_can_scan_all_items()
    {
        SimpleUser::create(['partition' => 'user-1', 'name' => 'Alice']);
        SimpleUser::create(['partition' => 'user-2', 'name' => 'Bob']);

        $all = SimpleUser::all();

        $this->assertCount(2, $all);
        $this->assertInstanceOf(Collection::class, $all);
        $this->assertInstanceOf(SimpleUser::class, $all->first());
    }

    #[Test]
    public function it_can_put_and_get_item_via_query_builder()
    {
        $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->putItem(['partition' => 'qb-1', 'name' => 'QueryBuilder']);

        $result = $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->getItem(['partition' => 'qb-1']);

        $this->assertEquals('QueryBuilder', $result['Item']['name']);
    }

    #[Test]
    public function it_can_increment_and_decrement()
    {
        $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->putItem(['partition' => 'counter-1', 'score' => 0]);

        $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->key(['partition' => 'counter-1'])
            ->increment('score', 5);

        $result = $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->getItem(['partition' => 'counter-1']);

        $this->assertEquals(5, $result['Item']['score']);

        $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->key(['partition' => 'counter-1'])
            ->decrement('score', 2);

        $result = $this->app['db']->connection('dynamodb')
            ->table('SimpleUser')
            ->getItem(['partition' => 'counter-1']);

        $this->assertEquals(3, $result['Item']['score']);
    }
}
