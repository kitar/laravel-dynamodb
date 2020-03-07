<?php

namespace Kitar\Dynamodb\Helpers;

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

    public function rewind()
    {
        $this->current = $this->start;
    }

    public function current()
    {
        return "{$this->prefix}{$this->current}";
    }

    public function key()
    {
        return $this->current;
    }

    public function next()
    {
        $this->current++;
    }

    public function valid()
    {
        return true;
    }
}
