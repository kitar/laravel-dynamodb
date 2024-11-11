<?php

namespace Attla\Dynamodb\Concerns;

use Illuminate\Support\Arr;

trait HasMeta
{
    /**
     * The @metadata attribute of AWS\Result response.
     *
     * @var mixed
     */
    private $meta;

    /**
     * Get @metadata attribute.
     *
     * @return mixed
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Get @metadata attribute.
     *
     * @return mixed
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Set @metadata attribute.
     *
     * @return mixed
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Determine if @metadata has any errors.
     *
     * @return bool
     */
    public function hasErrors() {
        $status = (int) Arr::get($this->meta(), '@metadata.statusCode');
        return $status >= 200 && $status < 300;
    }

    /**
     * Get LastEvaluatedKey from @metadata.
     *
     * @return bool
     */
    public function getLastEvaluatedKey()
    {
        return $this->meta['LastEvaluatedKey'] ?? null;
    }
}
