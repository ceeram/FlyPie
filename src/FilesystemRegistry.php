<?php

namespace WyriHaximus\FlyPie;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;

class FilesystemRegistry
{
    const CONFIGURE_KEY_PREFIX = 'WyriHaximus.FlyPie.';
    const INVALID_ARGUMENT_MSG = 'Filesystem "%s" has no client or factory or parameters specific to build a client';

    /**
     * Instance cache.
     *
     * @var array
     */
    protected static $instances = [];

    /**
     * Build and return a preconfigured adapter.
     *
     * @param string $alias The alias chosen for the adapter we want.
     *
     * @return AdapterInterface
     */
    public static function retrieve($alias)
    {
        if (!isset(static::$instances[$alias])) {
            static::$instances[$alias] = new Filesystem(static::create($alias));
        }

        return static::$instances[$alias];
    }

    /**
     * Gather the information to build an adapter.
     *
     * @param string $alias The alias chosen for the adapter we want.
     *
     * @throws \InvalidArgumentException Thrown when no matching configuration is found.
     *
     * @return AdapterInterface
     */
    protected static function create($alias)
    {
        $aliasConfigKey = static::CONFIGURE_KEY_PREFIX . $alias;
        if (!Configure::check($aliasConfigKey)) {
            throw new \InvalidArgumentException('Filesystem "' . $alias . '" not configured');
        }

        if (static::existsAndInstanceOf($aliasConfigKey)) {
            return Configure::read($aliasConfigKey . '.client');
        }

        if (Configure::check($aliasConfigKey . '.factory')) {
            return static::factory(Configure::read($aliasConfigKey . '.factory'));
        }

        if (self::existsAndVarsCount($aliasConfigKey)) {
            return static::adapter(static::read($aliasConfigKey, 'adapter'), static::read($aliasConfigKey, 'vars'));
        }

        throw new \InvalidArgumentException(sprintf(static::INVALID_ARGUMENT_MSG, $alias));
    }

    /**
     * Check if $aliasConfigKey exists and has the correct instance.
     *
     * @param string $aliasConfigKey Key to check for.
     *
     * @return boolean
     */
    protected static function existsAndInstanceOf($aliasConfigKey)
    {
        return Configure::check($aliasConfigKey . '.client') &&
            Configure::read($aliasConfigKey . '.client') instanceof AdapterInterface;
    }

    /**
     * Check if $aliasConfigKey exists and if it has vars defined.
     *
     * @param string $aliasConfigKey Key to check for.
     *
     * @return boolean
     */
    protected static function existsAndVarsCount($aliasConfigKey)
    {
        return Configure::check($aliasConfigKey . '.vars') &&
            count(Configure::read($aliasConfigKey . '.vars')) > 0;
    }

    /**
     * Read from Configure for given $aliasConfigKey . '.' . $field.
     *
     * @param string $aliasConfigKey Config alias key.
     * @param string $field          Config field.
     *
     * @return mixed
     */
    protected static function read($aliasConfigKey, $field)
    {
        return Configure::read($aliasConfigKey . '.' . $field);
    }

    /**
     * Adapter factory.
     *
     * @param callable|string|array $factory The factory to use and build the adapter.
     *
     * @return mixed
     */
    protected static function factory($factory)
    {
        $type = gettype($factory);
        if ($type == 'callable') {
            return $factory();
        }

        if ($type == 'string' && count(EventManager::instance()->listeners($factory)) > 0) {
            return EventManager::instance()->dispatch($factory)->result;
        }

        if (($type == 'string' || $type == 'array') && function_exists($factory)) {
            return call_user_func($factory);
        }
    }

    /**
     * Instantiate adapter.
     *
     * @param string $adapter Adapter to create.
     * @param array  $vars    Vars to pass to adapter.
     *
     * @throw \InvalidArgumentException Thrown when the given adapter class doesn't exists.
     *
     * @return AdapterInterface
     */
    protected static function adapter($adapter, array $vars)
    {
        if (!class_exists($adapter)) {
            $adapter = '\\WyriHaximus\\FlyPie\\Adapter\\' . $adapter;
        }

        if (class_exists($adapter)) {
            return (new $adapter())->create($vars);
        }

        throw new \InvalidArgumentException('Unknown adapter');
    }
}
