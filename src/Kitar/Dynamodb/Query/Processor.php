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
        $responseArray = $res->toArray();

        if (! empty($responseArray['Item'])) {
            $responseArray['Item'] = $this->marshaler->unmarshalItem($responseArray['Item']);
        }

        return $responseArray;
    }

    public function processMultipleItems(Result $res)
    {
        $responseArray = $res->toArray();

        foreach ($responseArray['Items'] as &$item) {
            $item = $this->marshaler->unmarshalItem($item);
        }

        return $responseArray;
    }
}
