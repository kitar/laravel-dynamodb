<?php

namespace Kitar\Dynamodb\Tests\Integration\Model;

use Kitar\Dynamodb\Model\Model;

class SimpleUser extends Model
{
    protected $table = 'SimpleUser';

    protected $primaryKey = 'partition';

    protected $fillable = [
        'partition', 'name', 'age', 'score', 'active', 'deleted', 'tags', 'address', 'extra',
    ];

    public $timestamps = false;
}
