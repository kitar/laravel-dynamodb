<?php

namespace Kitar\Dynamodb\Helpers;

use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * @var array
     */
    private $meta;

    /**
     * @param  array  $meta
     * @return $this
     */
    public function setMeta($meta)
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Get meta data.
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Get LastEvaluatedKey from meta
     */
    public function getLastEvaluatedKey()
    {
        return $this->meta['LastEvaluatedKey'] ?? null;
    }
}
