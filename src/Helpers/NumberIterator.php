<?php

namespace Attla\Dynamodb\Helpers;

class NumberIterator implements \Iterator
{
    private $start = 0;
    private $current = 0;
    private $prefix = '';

    public function __construct($start = 1, $prefix = '')
    {
        $this->start = $this->current = $start;
        $this->prefix = $prefix;
    }

    public function rewind(): void
    {
        $this->current = $this->start;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return "{$this->prefix}{$this->current}";
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->current;
    }

    public function next(): void
    {
        $this->current++;
    }

    public function valid(): bool
    {
        return true;
    }
}
