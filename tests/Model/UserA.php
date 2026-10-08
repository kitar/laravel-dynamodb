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

    public function scopeVoidActive($query)
    {
        $query->filter('status', '=', 'active');
    }

    public function scopeOpenOrRecent($query)
    {
        $query->filter('state', '=', 'open')->orFilter('recent', '=', true);
    }

    public function scopeOwnedBy($query, $owner)
    {
        return $query->filter('owner', '=', $owner);
    }

    public function scopeGuarded($query)
    {
        $query->condition('locked', '=', false)->orCondition('owner', '=', 'admin');
    }

    public function scopeOfPartition($query, $partition)
    {
        return $query->keyCondition('partition', '=', $partition);
    }

    public function scopeOfPartitionOrSort($query, $partition)
    {
        $query->keyCondition('partition', '=', $partition)->orKeyCondition('sort', '=', 1);
    }
}
