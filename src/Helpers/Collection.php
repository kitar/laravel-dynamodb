<?php

namespace Attla\Dynamodb\Helpers;

use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    use \Attla\Dynamodb\Concerns\HasMeta;
}
