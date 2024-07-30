<?php

namespace Kitar\Dynamodb\Concerns;

use Illuminate\Support\Arr;

trait HasMeta
{
    /**
     * The @metadata attribute of AWS\Result response.
     *
     * @var mixed
     */
    protected $meta;

    /**
     * Get @metadata attribute of AWS\Result response.
     *
     * @return mixed
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Set @metadata attribute from AWS\Result response.
     *
     * @return mixed
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;
    }

    /**
     * Determine if @metadata attribute from AWS\Result response.
     *
     * @return mixed
     */
    public function hasErrors() {
        $status = (int) Arr::get($this->meta(), '@metadata.statusCode');
        return $status >= 200 && $status < 300;
    }
}
