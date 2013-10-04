<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\DefinitionFactory;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Command to clear a collection cache region.
 *
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class CacheDefinitionFactory
{
    private static $simpleDrivers = array(
        'apc'       => true,
        'array'     => true,
        'xcache'    => true,
        'zenddata'  => true,
        'wincache'  => true,
    );

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $prefix;

    public function __construct($type, $prefix)
    {
        $this->type   = $type;
        $this->prefix = $prefix;
    }

    protected function getParam(array $configuration, $name, $default)
    {
        return (!empty($configuration[$name]))
            ? $configuration[$name]
            : $default;
    }

    protected function getReferenceName($name, $parameter = true)
    {
        if ($parameter) {
            return '%' . $this->prefix . $name . '%';
        }

        return $this->prefix . $name;
    }

    /**
     * @param string $type
     * @param string $prefix
     *
     * @return \Symfony\Bridge\Doctrine\DependencyInjection\DefinitionFactory\class|\Symfony\Bridge\Doctrine\DependencyInjection\DefinitionFactory\CacheDefinitionFactory
     *
     * @throws \InvalidArgumentException
     */
    public static function create($type, $prefix)
    {
        if (isset(self::$simpleDrivers[$type])) {
            return new CacheDefinitionFactory($type, $prefix);
        }

        $class = __NAMESPACE__ . '\\' . ucfirst($type) . 'CacheDefinitionFactory';

        if ( ! class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('"%s" is an unrecognized Doctrine cache driver.', $type));
        }

        return new $class($type, $prefix);
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array $configuration
     *
     * @return \Symfony\Component\DependencyInjection\Definition
     */
    public function createDefinition(ContainerBuilder $container, array $configuration)
    {
        return new Definition("%{$this->prefix}cache.{$this->type}.class%");
    }
}
