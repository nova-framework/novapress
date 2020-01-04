<?php

namespace Modules\Content\Platform;

use Nova\Container\Container;
use Nova\Support\Arr;

use Modules\Content\Platform\ContentType;

use Closure;
use InvalidArgumentException;


class ContentLabelManager
{
    /**
     * @var \Nova\Container\Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $types = array();

    /**
     * @var array
     */
    protected $labels = array();


    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function register($type, $callback)
    {
        $baseClass = ContentType::class;

        if ((! $callback instanceof Closure) && (! $callback instanceof $baseClass)) {
            throw new InvalidArgumentException("The callback must be a a closure or a subclass of [{$baseClass}]");
        }

        //
        else if (isset($this->types[$type])) {
            throw new InvalidArgumentException("The Content type [{$type}] is already registered");
        }

        $this->types[$type] = $callback;

        return $this;
    }

    public function forget($type)
    {
        unset($this->types[$type]);
    }

    public function all()
    {
        $key = $this->getCurrentLocale();

        return Arr::get($this->labels, $key, array());
    }

    public function get($type, $name, $default = null)
    {
        $labels = $this->resolveLabels($type);

        return Arr::get($labels, $name, $default);
    }

    protected function resolveLabels($type)
    {
        $key = sprintf('%s.%s', $this->getCurrentLocale(), $type);

        if (Arr::has($this->labels, $key)) {
            return Arr::get($this->labels, $key, array());
        }

        //
        else if (is_null($callback = Arr::get($this->types, $type))) {
           return array();
        }

        $result = call_user_func(
            ($callback instanceof Closure) ? $callback : array($callback, 'labels')
        );

        $labels = array_filter((array) $result, function ($key)
        {
            return (($key == 'name') || ($key == 'title'));

        }, ARRAY_FILTER_USE_KEY);

        Arr::set($this->labels, $key, $labels);

        return $labels;
    }

    protected function getCurrentLocale()
    {
        $language = $this->container['language'];

        return $language->getLocale();
    }
}
