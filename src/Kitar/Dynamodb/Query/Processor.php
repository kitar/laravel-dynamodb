<?php

namespace Kitar\Dynamodb\Query;

use Aws\Result;
use Aws\DynamoDb\Marshaler;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    public $marshaler;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    public function processSingleItem(Result $res, $model_class)
    {
        $responseArray = $res->toArray();

        if (! empty($responseArray['Item'])) {
            $item = $this->marshaler->unmarshalItem($responseArray['Item']);

            if ($model_class) {
                $item = (new $model_class)->newFromBuilder($item);
            }

            $responseArray['Item'] = $item;
        }

        return $responseArray;
    }

    public function processMultipleItems(Result $res, $model_class)
    {
        $responseArray = $res->toArray();

        foreach ($responseArray['Items'] as &$item) {
            $item = $this->marshaler->unmarshalItem($item);

            if ($model_class) {
                $item = (new $model_class)-newFromBuilder($item);
            }
        }

        return $responseArray;
    }
}
