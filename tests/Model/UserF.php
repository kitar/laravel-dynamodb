<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Support\Carbon;
use Kitar\Dynamodb\Model\Model;

class UserF extends Model
{
    protected $table = 'User';
    protected $primaryKey = 'partition';
    protected $fillable = [
        'partition', 'name',
    ];

    /**
     * Store updated_at as a Unix timestamp.
     */
    public function setUpdatedAtAttribute($value)
    {
        $this->attributes['updated_at'] = Carbon::parse($value)->timestamp;
    }
}
