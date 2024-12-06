<?php

namespace Attla\Dynamodb\Pagination;

use Aws\DynamoDb\Marshaler;
use Attla\Dynamodb\Helpers\Collection;
use Attla\Pincryp\Facade as Pincryp;
use Illuminate\Pagination\Paginator as BasePagination;
use Illuminate\Support\Arr;

class Paginator extends BasePagination
{
    /**
     * Store the Marshaler instance
     *
     * @var \Aws\DynamoDb\Marshaler
     */
    protected static $marshaler;

    /**
     * The page size resolver callback
     *
     * @var \Closure
     */
    protected static $pageSizeResolver;

    /** @inheritdoc */
    public function __construct($items, $perPage, $currentPage = null, array $options = [])
    {
        if (!empty($currentPage)) {
            $currentPage = static::encodePage($currentPage);
        }

        parent::__construct($items, $perPage, $currentPage, $options);
    }

    /**
     * Retrieve the Marshaler instance
     *
     * @return \Aws\DynamoDb\Marshaler
     */
    public static function marshaler()
    {
        if (empty(static::$marshaler)) {
            static::$marshaler = new Marshaler();
        }

        return static::$marshaler;
    }

    /**
     * Encode the page
     *
     * @param string|null $page
     * @return string|null
     */
    public static function encodePage($page)
    {
        if (empty($page)) {
            return null;
        }

        if (is_array($page)) {
            $page = static::marshaler()->unmarshalItem($page);
        }

        return Pincryp::encode([$page, static::uriHash()]);
    }

    /**
     * Decode the page
     *
     * @param string|null $page
     * @return string|null
     */
    public static function decodePage($page)
    {
        if (
            !is_string($page)
            || !Arr::isList($page = Pincryp::decode($page, true))
            || count($page) != 2
        ) {
            return null;
        }

        [$page, $hash] = $page;
        if (!static::verifyUri($hash)) {
            return null;
        }

        return static::marshaler()->marshalItem($page);
    }

    /**
     * Get current URI hash
     *
     * @return string
     */
    protected static function uriHash()
    {
        return base64_encode(md5(static::resolveCurrentPath(), true));
    }

    /**
     * Checks the integrity of current request
     *
     * @return string
     */
    protected static function verifyUri($hash)
    {
        return static::uriHash() === $hash;
    }

    /**
     * Resolve the page size or return the default value
     *
     * @param string|int|null $default
     * @return int
     */
    public static function resolvePageSize($default = null)
    {
        if (isset(static::$pageSizeResolver)) {
            return (int) call_user_func(static::$pageSizeResolver);
        }

        return $default;
    }

    /**
     * Set with query string resolver callback
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function pageSizeResolver(\Closure $resolver)
    {
        static::$pageSizeResolver = $resolver;
    }

    /**
     * Determine if the given value is a valid page number
     *
     * @param string $page
     * @return bool
     */
    protected function isValidPageNumber($page)
    {
        return !empty($page);
    }

    /** @inheritdoc */
    protected function setItems($items)
    {
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $meta = $items->getMeta();
        $this->items = $items->slice(0, $this->perPage)->setMeta($meta);
    }

    /**
     * Get the current page for the request
     *
     * @param string $currentPage
     * @return string
     */
    protected function setCurrentPage($currentPage)
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage();
        return $this->isValidPageNumber($currentPage) ? $currentPage : null;
    }

    /**
     * Resolve the current page or return the default value
     *
     * @param string $pageName
     * @param string|null $default
     * @return string
     */
    public static function resolveCurrentPage($pageName = 'page', $default = null)
    {
        if (isset(static::$currentPageResolver)) {
            return static::decodePage(call_user_func(static::$currentPageResolver, $pageName));
        }

        return $default;
    }

    /** @inheritdoc */
    public function nextPageUrl()
    {
        if (
            method_exists($this->items, 'getLastEvaluatedKey')
            and $last = $this->items->getLastEvaluatedKey()
        ) {
            return static::encodePage($last);
        }

        return null;
    }

    /** @inheritdoc */
    public function previousPageUrl()
    {
        return null;
    }

    /** @inheritdoc */
    public function hasMorePages()
    {
        return method_exists($this->items, 'getLastEvaluatedKey')
            && $this->items->getLastEvaluatedKey();
    }

    /**
     * Get the number of the first item in the slice
     *
     * @return null
     */
    public function firstItem()
    {
        return null;
    }

    /**
     * Get the number of the last item in the slice
     *
     * @return null
     */
    public function lastItem()
    {
        return null;
    }

    /** @inheritdoc */
    public function onFirstPage()
    {
        return !$this->currentPage();
    }

    /**
     * Get the URL for a given page number
     *
     * @param  int  $page
     * @return string
     */
    public function url($page = null)
    {
        if (empty($page)) {
            $page = '';
        }
        $parameters = [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return trim($this->path()
            . (str_contains($this->path(), '?') ? '&' : '?')
            . Arr::query(array_filter($parameters))
            . $this->buildFragment(), '#?');
    }

    /** @inheritdoc */
    public function loadMorph($relation, $relations)
    {
        return $this;
    }

    /** @inheritdoc */
    public function loadMorphCount($relation, $relations)
    {
        return $this;
    }

    /**
     * Get the instance as an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'data' => $this->items->toArray(),
            'next_page' => $this->nextPageUrl(),
            'size' => $this->perPage(),
        ];
    }
}

