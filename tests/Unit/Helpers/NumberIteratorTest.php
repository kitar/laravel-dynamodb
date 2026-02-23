<?php

namespace Kitar\Dynamodb\Tests\Unit\Helpers;

use Kitar\Dynamodb\Helpers\NumberIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NumberIteratorTest extends TestCase
{
    #[Test]
    public function it_can_use_prefix()
    {
        $iterator = new NumberIterator(1, '#');

        $this->assertEquals('#1', $iterator->current());
    }

    #[Test]
    public function it_can_increment()
    {
        $iterator = new NumberIterator(1, ':');

        $iterator->next();

        $this->assertEquals(':2', $iterator->current());

        $iterator->next();

        $this->assertEquals(':3', $iterator->current());
    }

    #[Test]
    public function key_returns_number_without_prefix()
    {
        $iterator = new NumberIterator(1, '#');

        $iterator->next();

        $this->assertEquals(2, $iterator->key());
    }

    #[Test]
    public function it_can_rewind()
    {
        $iterator = new NumberIterator(1, '#');

        $iterator->next();
        $iterator->next();
        $iterator->next();
        $iterator->rewind();

        $this->assertEquals('#1', $iterator->current());
    }

    #[Test]
    public function it_is_always_valid()
    {
        $iterator = new NumberIterator(1);

        $this->assertTrue($iterator->valid());
    }
}
