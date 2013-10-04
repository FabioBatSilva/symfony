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
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Memcache cache definition factory.
 *
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class MemcacheCacheDefinitionFactory extends CacheDefinitionFactory
{
    /**
     * {@inheritDoc}
     */
    public function createDefinition(ContainerBuilder $container, array $configuration)
    {
        $instanceClass  = $this->getParam($configuration, 'instance_class', $this->getReferenceName('cache.memcache_instance.class'));
        $driverClass    = $this->getParam($configuration, 'class', $this->getReferenceName('cache.memcache.class'));
        $host           = $this->getParam($configuration, 'host', $this->getReferenceName('cache.memcache_host'));
        $port           = $this->getParam($configuration, 'port', $this->getReferenceName('cache.memcache_port'));
        $instanceId     = $this->getReferenceName('cache.memcache_instance', false);
        $instanceDef    = new Definition($instanceClass);
        $cacheDef       = new Definition($driverClass);

        $container->setDefinition($instanceId, $instanceDef);
        $instanceDef->addMethodCall('connect', array($host, $port));
        $cacheDef->addMethodCall('setMemcache', array(new Reference($instanceId)));

        return $cacheDef;
    }
}
