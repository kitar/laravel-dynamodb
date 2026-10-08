<?php

namespace Kitar\Dynamodb\Tests\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Kitar\Dynamodb\Model\Model;

class UserE extends Model
{
    use HasUuids;

    protected $table = 'User';
    protected $primaryKey = 'partition';
    protected $fillable = [
        'partition', 'name',
    ];
}
