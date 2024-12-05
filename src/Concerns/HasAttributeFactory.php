<?php

namespace Attla\Dynamodb\Concerns;

use Attla\Support\Invoke;

trait HasAttributeFactory
{
    protected array $_properties = [];

    protected $attributeFactory = [];

    /** @inheritdoc */
    public function fill(array $attributes)
    {
        $this->_properties = $attributes;
        return parent::fill($attributes);
    }

    /** @inheritdoc */
    public function setAttribute($key, $value)
    {
        if (!empty($this->attributes[$key])) {
            $this->attributes[$key] = $this->_properties[$key] = null;
        }

        return parent::setAttribute($key, $this->resolveAttribute($key, $value));
    }

    /** @inheritdoc */
    public function getAttribute($key)
    {
        return parent::getAttribute($key) ?? $this->resolveAttribute($key);
    }

    /**
     * Retrieve a atribute mask factory
     *
     * @param string $key
     * @return string|array<string, string>
     */
    protected function attributeFactory($key = null)
    {
        $factory = $this->attributeFactory;
        return $key ? $factory[$key] ?? null : $factory;
    }

    /**
     * Resolve the mask if necessary
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function resolveAttribute($key, $value = null) {
        if (
            empty($factory = $this->attributeFactory($key))
            || $this->matchesMask($factory, $value)
        ) {
            return $value;
        }

        return $this->resolvePlaceholder($factory, $value, $key);
    }

    /**
     * Check if value is masked
     *
     * @param string $mask
     * @param mixed $value
     * @return bool
     */
    protected function matchesMask($mask, $value)
    {
        $pattern = preg_replace('/\{[^}]+\}/', '.*', preg_quote($mask, '/'));
        return (bool) preg_match(
            '/^' . str_replace('\.*', '.*', $pattern) . '$/',
            $value
        );
    }

    /**
     * Resolve attribute mask
     *
     * @param string $mask
     * @param mixed|null $value
     * @param string|null $key
     * @return mixed
     */
    protected function resolvePlaceholder($mask, $value = null, $key = null)
    {
        return preg_replace_callback('/\{(\w+(?:::\w+|->\w+)?|\w+)\(?(\w+)?\)?\}/', function ($matches) use ($mask, $value, $key) {
            $function = isset($matches[2]) ? $matches[1] : null;
            $property = $matches[2] ?? $matches[1];

            $value = $this->_properties[$property]
                ?? (!empty($this->attributes[$property])
                        ? $this->$property :
                        ($property == $key ? $value : null));

            if (empty($value)) {
                return '';
            }

            if ($function) {
                return Invoke::resolve($function, $value);
            }

            return $value == $mask ? '' : $value;
        }, $mask);
    }
}
