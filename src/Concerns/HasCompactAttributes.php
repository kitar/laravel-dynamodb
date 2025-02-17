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
        static::builded(function ($model) {
            $model->hidden[] = 'v';
            $model->guarded = array_merge($model->timestamps(), $model->guarded);
        });

        $prepare = fn ($model) => $model->prepareCompacts();
        static::creating($prepare);
        static::updating($prepare);

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
        return array_filter([$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()], fn ($col) => is_string($col));
    }

    /**
     * Get fillable attributes columns
     *
     * @return array<string>
     */
    protected function fields()
    {
         return array_values(array_diff(
            array_unique(array_merge($this->getFillable(), $this->timestamps())),
            $this->getKeySchema(),
        ));
    }

    /**
     * Prepare the compact attributes to persiste on database
     *
     * @return void
     */
    public function prepareCompacts()
    {
        $this->updateTimestamps();
        $this->attributes = array_merge($this->_c, $this->attributes);
        $data = [];
        $fillable = $this->fields();

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
