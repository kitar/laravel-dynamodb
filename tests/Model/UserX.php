<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Kitar\Dynamodb\Model\Model;

class UserX extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';
}
