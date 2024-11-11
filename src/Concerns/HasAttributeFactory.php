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

    protected function attributeFactory($key = null)
    {
        $factory = $this->attributeFactory;
        return $key ? $factory[$key] ?? null : $factory;
    }

    protected function resolveAttribute($key, $value = null) {
        if (empty($factory = $this->attributeFactory($key))) {
            return $value;
        }

        return $this->resolvePlaceholder($factory, $value, $key);
    }

    protected function resolvePlaceholder($template, $value = null, $key = null)
    {
        return preg_replace_callback('/\{(\w+(?:::\w+|->\w+)?|\w+)\(?(\w+)?\)?\}/', function ($matches) use ($template, $value, $key) {
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

            return $value == $template ? '' : $value;
        }, $template);
    }
}
