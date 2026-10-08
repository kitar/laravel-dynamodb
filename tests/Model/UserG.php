<?php

namespace Kitar\Dynamodb\Tests\Model;

use Kitar\Dynamodb\Model\Model;

class UserG extends Model
{
    protected $table = 'User';
    protected $primaryKey = 'partition';
    protected $fillable = [
        'partition', 'price',
    ];
    protected $casts = [
        'price' => CentsCast::class,
    ];
}
