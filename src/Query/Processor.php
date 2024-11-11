<?php

namespace Attla\Dynamodb\Query;

use Aws\DynamoDb\Marshaler;
use Aws\Result;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Attla\Dynamodb\Helpers\Collection;
use Attla\Dynamodb\Helpers\Data;

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
        if (!empty($responseArray['Item'])) {
            $responseArray['Item'] = $this->marshaler->unmarshalItem($responseArray['Item']);
        }

        if (!empty($responseArray['Items'])) {
            foreach ($responseArray['Items'] as &$item) {
                $item = $this->marshaler->unmarshalItem($item);
            }
        }

        if (!empty($responseArray['Attributes'])) {
            $responseArray['Attributes'] = $this->marshaler->unmarshalItem($responseArray['Attributes']);
        }

        if (!empty($responseArray['Responses'])) {
            foreach ($responseArray['Responses'] as &$items) {
                foreach ($items as &$item) {
                    $item = $this->marshaler->unmarshalItem($item);
                }
            }
        }

        return $responseArray;
    }

    public function processSingleItem(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass) && !empty($response['Item'])) {
            return new Data($response['Item']);
        }

        if (!empty($response['Item'])) {
            return (new $modelClass)->newFromBuilder($response['Item']);
        }

        if (!empty($response['Attributes'])) {
            return $response;
        }
    }

    public function processMultipleItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);
        $items = new Collection([]);

        if (empty($modelClass)) {
            foreach ($response['Items'] as $item) {
                $items->push(new Data($item));
            }
        } else if (count($response['Items']) && $response['Items'][0] instanceof Model) {
            foreach ($response['Items'] as $item) {
                $items->push($item);
            }
        } else {
            foreach ($response['Items'] as $item) {
                $items->push((new $modelClass)->newFromBuilder($item));
            }
        }

        unset($response['Items']);
        return $items->setMeta($response);
    }

    public function processBatchGetItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);
        $items = new Collection([]);

        if (empty($modelClass)) {
            foreach ($response['Responses'] as $_ => $table_items) {
                foreach ($table_items as $item) {
                    $items->push(new Data($item));
                }
            }
        } else {
            foreach ($response['Responses'] as $_ => $table_items) {
                foreach ($table_items as $item) {
                    $items->push((new $modelClass)->newFromBuilder($item));
                }
            }
        }

        unset($response['Responses']);
        return $items->setMeta($response);
    }
}
