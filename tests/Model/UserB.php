<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Kitar\Dynamodb\Model\Model;

class UserB extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';

    protected $primaryKey = 'partition';

    protected $sortKey = 'sort';

    protected $fillable = [
        'partition', 'sort', 'name', 'password',
    ];
}
