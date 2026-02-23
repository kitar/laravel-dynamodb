<?php

namespace Kitar\Dynamodb\Tests\Unit\Helpers;

use Kitar\Dynamodb\Helpers\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    #[Test]
    public function it_can_set_and_get_meta()
    {
        $items = new Collection([]);

        $this->assertNull($items->getMeta());

        $meta = ['LastEvaluatedKey' => ['id' => ['S' => '1']]];

        $items->setMeta($meta);

        $this->assertSame($meta, $items->getMeta());
    }

    #[Test]
    public function it_can_get_last_evaluated_key()
    {
        $items = new Collection([]);

        $this->assertNull($items->getLastEvaluatedKey());

        $lastEvaluatedKey = ['id' => ['S' => '1']];

        $items->setMeta(['LastEvaluatedKey' => $lastEvaluatedKey]);

        $this->assertSame($lastEvaluatedKey, $items->getLastEvaluatedKey());
    }
}
