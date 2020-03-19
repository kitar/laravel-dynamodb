<?php

namespace Kitar\Dynamodb\Tests\Model;

use Kitar\Dynamodb\Model\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class UserX extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';
}
