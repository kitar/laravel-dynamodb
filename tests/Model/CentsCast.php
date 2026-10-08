<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\DeviatesCastableAttributes;

/**
 * Stores an amount of money as cents.
 */
class CentsCast implements CastsAttributes, DeviatesCastableAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value / 100;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [$key => (int) round($value * 100)];
    }

    public function increment($model, string $key, $value, array $attributes)
    {
        return [$key => $attributes[$key] + (int) round($value * 100)];
    }

    public function decrement($model, string $key, $value, array $attributes)
    {
        return [$key => $attributes[$key] - (int) round($value * 100)];
    }
}
