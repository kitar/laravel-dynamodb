<?php

namespace Kitar\Dynamodb\Tests\Integration\Model;

use Kitar\Dynamodb\Model\Model;

class CompositeUser extends Model
{
    protected $table = 'CompositeUser';

    protected $primaryKey = 'partition';

    protected $sortKey = 'sort';

    protected $fillable = [
        'partition', 'sort', 'name',
    ];

    public $timestamps = false;
}
