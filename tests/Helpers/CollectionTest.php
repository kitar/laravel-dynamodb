<?php

namespace Kitar\Dynamodb\Tests\Helpers;

use Kitar\Dynamodb\Helpers\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    /** @test */
    public function it_can_set_and_get_meta()
    {
        $items = new Collection([]);

        $this->assertNull($items->getMeta());

        $meta = ['LastEvaluatedKey' => ['id' => ['S' => '1']]];

        $items->setMeta($meta);

        $this->assertSame($meta, $items->getMeta());
    }

    /** @test */
    public function it_can_get_last_evaluated_key()
    {
        $items = new Collection([]);

        $this->assertNull($items->getLastEvaluatedKey());

        $lastEvaluatedKey = ['id' => ['S' => '1']];

        $items->setMeta(['LastEvaluatedKey' => $lastEvaluatedKey]);

        $this->assertSame($lastEvaluatedKey, $items->getLastEvaluatedKey());
    }
}
