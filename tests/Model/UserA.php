<?php

namespace Kitar\Dynamodb\Tests\Model;

use Kitar\Dynamodb\Model\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class UserA extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';
    protected $primaryKey = 'partition';
    protected $fillable = [
        'partition', 'name', 'password', 'status',
    ];

    public function scopeActive($query)
    {
        return $query->filter('status', '=', 'active');
    }

    public function scopeByName($query, $name)
    {
        return $query->filter('name', '=', $name);
    }
}
