<?php

namespace Kitar\Dynamodb\Query;

use Aws\DynamoDb\Marshaler;
use Aws\Result;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Kitar\Dynamodb\Helpers\Collection;

class Processor extends BaseProcessor
{
    public $marshaler;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    protected function unmarshal(Result $res)
    {
        $responseArray = $res->toArray();

        if (! empty($responseArray['Item'])) {
            $responseArray['Item'] = $this->marshaler->unmarshalItem($responseArray['Item']);
        }

        if (! empty($responseArray['Items'])) {
            foreach ($responseArray['Items'] as &$item) {
                $item = $this->marshaler->unmarshalItem($item);
            }
        }

        if (! empty($responseArray['Attributes'])) {
            $responseArray['Attributes'] = $this->marshaler->unmarshalItem($responseArray['Attributes']);
        }

        if (! empty($responseArray['Responses'])) {
            foreach ($responseArray['Responses'] as &$items) {
                foreach ($items as &$item) {
                    $item = $this->marshaler->unmarshalItem($item);
                }
            }
        }

        return $responseArray;
    }

    public function processCount(Result $awsResponse, $modelClass = null) {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        if (! empty($response['Count'])) {
            return $response['Count'];
        }
    }

    public function processSingleItem(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        if (! empty($response['Item'])) {
            $item = (new $modelClass)->newFromBuilder($response['Item']);
            unset($response['Item']);
            $item->setMeta($response ?? null);

            return $item;
        }

        if (! empty($response['Attributes'])) {
            return $response;
        }
    }

    public function processMultipleItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        $items = new Collection([]);

        foreach ($response['Items'] as $item) {
            $item = (new $modelClass)->newFromBuilder($item);
            $items->push($item);
        }

        unset($response['Items']);

        $items = $items->map(function ($item) use ($response) {
            $item->setMeta($response);

            return $item;
        });

        // set meta at the collection level
        $items->setMeta($response);

        return $items;
    }

    public function processBatchGetItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        $items = collect();

        foreach ($response['Responses'] as $_ => $table_items) {
            foreach ($table_items as $item) {
                $item = (new $modelClass)->newFromBuilder($item);
                $items->push($item);
            }
        }

        unset($response['Responses']);

        return $items->map(function ($item) use ($response) {
            $item->setMeta($response);

            return $item;
        });
    }
}
