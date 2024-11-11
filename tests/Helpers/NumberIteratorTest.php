<?php

namespace Attla\Dynamodb\Tests\Helpers;

use Attla\Dynamodb\Helpers\NumberIterator;
use PHPUnit\Framework\TestCase;

class NumberIteratorTest extends TestCase
{
    /** @test */
    public function it_can_use_prefix()
    {
        $iterator = new NumberIterator(1, '#');

        $this->assertEquals('#1', $iterator->current());
    }

    /** @test */
    public function it_can_increment()
    {
        $iterator = new NumberIterator(1, ':');

        $iterator->next();

        $this->assertEquals(':2', $iterator->current());

        $iterator->next();

        $this->assertEquals(':3', $iterator->current());
    }

    /** @test */
    public function key_returns_number_without_prefix()
    {
        $iterator = new NumberIterator(1, '#');

        $iterator->next();

        $this->assertEquals(2, $iterator->key());
    }

    /** @test */
    public function it_can_rewind()
    {
        $iterator = new NumberIterator(1, '#');

        $iterator->next();
        $iterator->next();
        $iterator->next();
        $iterator->rewind();

        $this->assertEquals('#1', $iterator->current());
    }

    /** @test */
    public function it_is_always_valid()
    {
        $iterator = new NumberIterator(1);

        $this->assertTrue($iterator->valid());
    }
}
