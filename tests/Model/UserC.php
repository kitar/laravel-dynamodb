<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Kitar\Dynamodb\Model\Model;

class UserC extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';

    protected $primaryKey = 'partition';

    protected $sortKey = 'sort';

    protected $sortKeyDefault = 'sort_default';

    protected $fillable = [
        'partition', 'sort', 'name', 'password',
    ];
}
