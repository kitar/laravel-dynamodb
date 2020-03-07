<?php

namespace Kitar\Dynamodb\Query;

use Aws\Result;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    public $marshaler;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    public function processSingleItem(Result $res)
    {
        $item = $res['Item'];

        if (empty($item)) {
            return null;
        }

        return new Collection(
            $this->marshaler->unmarshalItem($item)
        );
    }

    public function processMultipleItems(Result $res)
    {
        $items = $res['Items'];

        if (empty($items)) {
            return [];
        }

        foreach ($items as &$item) {
            $item = $this->marshaler->unmarshalItem($item);
        }

        return $items;
    }
}
