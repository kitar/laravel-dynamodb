<?php

namespace Attla\Dynamodb\Concerns;

trait HasCompactAttributes
{
    /**
     * Store the compacted attributes
     *
     * @var array<mixed>
     */
    protected $_c = [];

    /**
     * Replace maps of types
     *
     * @var array<string, string>
     */
    protected $typeMap = [
        // Null
        'null,' => 'N,',
        ',null' => ',N',
        'null]' => 'N]',
        // True
        'true,' => 'T,',
        ',true' => ',T',
        'true]' => 'T]',
        // False
        'false,' => 'X,',
        ',false' => ',X',
        'false]' => 'X]',
        // Array/Object
        '[],' => 'O,',
        ',[]' => ',O',
        '[]]' => 'O]',
    ];

    /**
     * Json options for encode/decode
     *
     * @var int
     */
    protected $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Boot compact attributes
     *
     * @return void
     */
    public static function bootHasCompactAttributes()
    {
        static::building(function ($model) {
            $model->fillable(array_merge($model->getFillable(), $model->timestamps()));
        });

        static::builded(function ($model) {
            $model->hidden[] = 'v';
            $model->guard(array_merge($model->getGuarded(), $model->timestamps()));

        });

        $prepare = fn($event) => fn ($model) => $model->prepareCompacts($event);
        static::creating($prepare('create'));
        static::updating($prepare('update'));

        $load = fn ($model) => $model->loadCompacts();
        static::retrieved($load);
        static::created($load);
        static::updated($load);
    }

    /**
     * Load compact attribute on model
     *
     * @return void
     */
    public function loadCompacts()
    {
        if (!empty($value = $this->attributes['v'] ?? [])) {
            $value = $this->decodeValue($value);
            $fillable = $this->fields();
            foreach ($fillable as $index => $field) {
                $this->$field = $value[$index] ?? null;
            }
        }
    }

    /**
     * Get fillable timestamps columns
     *
     * @return array<string>
     */
    protected function timestamps()
    {
        return array_filter([static::CREATED_AT, static::UPDATED_AT], fn ($col) => is_string($col));
    }

    /**
     * Get fillable attributes columns
     *
     * @return array<string>
     */
    protected function fields()
    {
         return array_values(array_diff(
            array_unique(array_merge($this->getFillable(), $timestamps = $this->timestamps())),
            array_merge($this->getKeySchema(), array_values(array_diff($this->getGuarded(), $timestamps)))
        ));
    }

    /**
     * Prepare the compact attributes to persiste on database
     *
     * @param string|null $event
     * @return void
     */
    public function prepareCompacts(string $event = null)
    {
        $data = [];
        $fillable = $this->fields();
        $this->attributes = array_merge($this->_c, $this->attributes);

        if ($event === 'create' && in_array(static::CREATED_AT, $fillable)) {
            $this->updateTimestamps();
            $this->setUpdatedAt(null);
        } else if ($event === 'update') {
            $this->syncOriginal();
            $this->updateTimestamps();
            $this->fillable = array_values(array_diff($this->fillable, $this->timestamps()));
        }

        $array = $this->toArray();
        foreach ($fillable as $field) {
            $data[] = $array[$field] ?? null;
        }

        $this->_c = $this->attributes;
        $this->attributes = [
            'v' => $this->encodeValue($data),
            'pk' => $this->pk,
            'sk' => $this->sk,
        ];
    }

    /**
     * Get the name of the "created at" column
     *
     * @return string|null
     */
    public function getCreatedAtColumn()
    {
        return in_array($column = static::CREATED_AT, $this->fillable) ? $column : null;
    }

    /**
     * Get the name of the "updated at" column
     *
     * @return string|null
     */
    public function getUpdatedAtColumn()
    {
        return in_array($column = static::UPDATED_AT, $this->fillable) ? $column : null;
    }

    /**
     * Maps values ​​for compression to save on database
     *
     * @param mixed $value
     * @return mixed
     */
    protected function encodeValue($value)
    {
        $value = $this->zipArray($value);
        $value = json_encode($value, $this->jsonOptions);

        $value = preg_replace('/(,|\[)"(\^\d+)"(\]|,|$)/', '$1$2$3', $value);

        $value = str_replace('"', $dq = '~TDQ~', $value);
        $value = str_replace("'", '"', $value);
        $value = str_replace($dq, "'", $value);
        $value = str_replace("\'", "^'", $value);

        return strtr($value, $this->typeMap);
    }

    /**
     * Converts compressed values ​​back to their original format
     *
     * @param mixed $value
     * @return mixed
     */
    protected function decodeValue($value)
    {
        $value = strtr($value, array_flip($this->typeMap));

        $value = str_replace('"', $sq = '~TSQ~', $value);
        $value = str_replace("'", '"', $value);
        $value = str_replace($sq, "'", $value);
        $value = str_replace('^"', '\"', $value);
        $value = preg_replace('/(,|\[)(\^\d+)(\]|,|$)/', '$1"$2"$3', $value);

        $value = json_decode($value, true, 512, $this->jsonOptions);

        return $this->unzipArray($value);
    }

    /**
     * Zip the attribute array
     *
     * @param array $array
     * @return array
     */
    protected function zipArray(array $array): array {
        $seen = [];
        $result = [];

        foreach ($array as $index => $item) {
            $length = match (gettype($item)) {
                'string' => strlen($item),
                'array' => count($item),
                'array' => count($item),
                'stdClass', 'object' => count(get_object_vars($item)),
                'integer', 'double', 'float' => strlen((string) $item),
                default => 0,
            };

            if ($length > 2 && ($pos = array_search($item, $seen, true)) !== false) {
                $result[] = "^$pos";
            } else {
                if (!in_array($item, [null, true, false], true)) {
                    $seen[$index] = $item;
                }
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Unzip the attribute array
     *
     * @param array $array
     * @return array
     */
    protected function unzipArray(array $array): array {
        foreach ($array as &$item) {
            $pos = is_string($item) && str_starts_with($item, '^')
                ? (int) substr($item, 1)
                : -1;

            if ($pos > -1 && isset($array[$pos]) && !str_starts_with($val = $array[$pos], '^')) {
                $item = $val;
            }
        }

        return $array;
    }
}
