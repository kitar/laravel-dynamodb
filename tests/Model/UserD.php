<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Kitar\Dynamodb\Model\Model;

class UserD extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';

    protected $primaryKey = 'partition';

    protected $fillable = [
        'partition',
    ];

    public $timestamps = false;
}
