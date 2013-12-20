<?php

/*
 * This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class DoctrineExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension
     */
    private $extension;

    protected function setUp()
    {
        parent::setUp();

        $this->extension = $this
            ->getMockBuilder('Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension')
            ->setMethods(array(
                'getMappingResourceConfigDirectory',
                'getObjectManagerElementName',
                'getMappingObjectDefaultName',
                'getMappingResourceExtension',
                'load',
            ))
            ->getMock()
        ;

        $this->extension->expects($this->any())
            ->method('getObjectManagerElementName')
            ->will($this->returnCallback(function($name) {
                 return 'doctrine.orm.'.$name;
            }));
    }

    public function providerBasicDrivers()
    {
        return array(
            array('doctrine.orm.cache.apc.class',       array('type' => 'apc')),
            array('doctrine.orm.cache.array.class',     array('type' => 'array')),
            array('doctrine.orm.cache.xcache.class',    array('type' => 'xcache')),
            array('doctrine.orm.cache.wincache.class',  array('type' => 'wincache')),
            array('doctrine.orm.cache.zenddata.class',  array('type' => 'zenddata')),
            array('doctrine.orm.cache.redis.class',     array('type' => 'redis'),     array('setRedis')),
            array('doctrine.orm.cache.memcache.class',  array('type' => 'memcache'),  array('setMemcache')),
            array('doctrine.orm.cache.memcached.class', array('type' => 'memcached'), array('setMemcached')),
        );
    }

    /**
     * @param string $class
     * @param array  $config
     *
     * @dataProvider providerBasicDrivers
     */
    public function testLoadBasicCacheDriver($class, array $config, array $expectedCalls = array())
    {
        $container      = $this->createContainer();
        $cacheName      = 'metadata_cache';
        $objectManager  = array(
            'name'                  => 'default',
            'metadata_cache_driver' => $config
        );

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $this->assertTrue($container->hasDefinition('doctrine.orm.default_metadata_cache'));

        $definition      = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $defCalls        = $definition->getMethodCalls();
        $expectedCalls[] = 'setNamespace';
        $actualCalls     = array_map(function($call){
            return $call[0];
        }, $defCalls);

        $this->assertFalse($definition->isPublic());
        $this->assertEquals("%$class%", $definition->getClass());

        foreach (array_unique($expectedCalls) as $call) {
            $this->assertContains($call, $actualCalls);
        }
    }

    public function testServiceCacheDriver()
    {
        $cacheName      = 'metadata_cache';
        $container      = $this->createContainer();
        $definition     = new Definition('%doctrine.orm.cache.apc.class%');
        $objectManager  = array(
            'name'                  => 'default',
            'metadata_cache_driver' => array(
                'type' => 'service',
                'id'   => 'service_driver'
            )
        );

        $container->setDefinition('service_driver', $definition);

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $this->assertTrue($container->hasAlias('doctrine.orm.default_metadata_cache'));
    }

    public function testRiakCache()
    {
        $cacheName      = 'metadata_cache';
        $container      = $this->createContainer();
        $objectManager  = array(
            'name'                  => 'default',
            'metadata_cache_driver'     => array(
                'type'                  => 'riak',
                'host'                  => 'localhost',
                'port'                  => '8087',
                'class'                 => 'MyRiakCache',
                'instance_class'        => 'MyRiakConnection',
                'keyspace_name'         => 'my_bucket_metadata',
                'keyspace_class'        => 'MyBucketConnection',
            )
        );

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $driverDef = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $connDef   = $container->getDefinition('doctrine.orm.default_riak_connection');
        $bucketDef = $container->getDefinition('doctrine.orm.default_riak_bucket');

        $this->assertEquals('MyRiakCache', $driverDef->getClass());
        $this->assertEquals('MyRiakConnection', $connDef->getClass());
        $this->assertEquals('MyBucketConnection', $bucketDef->getClass());

        $this->assertEquals('getBucket', $bucketDef->getFactoryMethod());
        $this->assertEquals('doctrine.orm.default_riak_connection', $bucketDef->getFactoryService());

        $this->assertEquals(array('localhost', '8087'), $connDef->getArguments());
        $this->assertEquals(array('my_bucket_metadata'), $bucketDef->getArguments());
        $this->assertEquals(array('doctrine.orm.default_riak_bucket'), $driverDef->getArguments());
    }

    public function testRiakCacheDefaults()
    {
        $cacheName      = 'metadata_cache';
        $container      = $this->createContainer();
        $objectManager  = array(
            'name'                  => 'default',
            'metadata_cache_driver' => array(
                'type'              => 'riak'
            )
        );

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $driverDef = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $connDef   = $container->getDefinition('doctrine.orm.default_riak_connection');
        $bucketDef  = $container->getDefinition('doctrine.orm.default_riak_bucket');

        $this->assertEquals("%doctrine.orm.cache.riak.class%", $driverDef->getClass());
        $this->assertEquals("%doctrine.orm.cache.riak_instance.class%", $connDef->getClass());
        $this->assertEquals("%doctrine.orm.cache.riak_keyspace.class%", $bucketDef->getClass());

        $this->assertEquals('getBucket', $bucketDef->getFactoryMethod());
        $this->assertEquals('doctrine.orm.default_riak_connection', $bucketDef->getFactoryService());

        $this->assertEquals(array('%doctrine.orm.cache.riak_host%', '%doctrine.orm.cache.riak_port%'), $connDef->getArguments());
        $this->assertEquals(array('doctrine_orm_default_metadata_cache'), $bucketDef->getArguments());
        $this->assertEquals(array('doctrine.orm.default_riak_bucket'), $driverDef->getArguments());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage "unrecognized_type" is an unrecognized Doctrine cache driver.
     */
    public function testUnrecognizedCacheDriverException()
    {
        $cacheName      = 'metadata_cache';
        $container      = $this->createContainer();
        $objectManager  = array(
            'name'                  => 'default',
            'metadata_cache_driver' => array(
                'type' => 'unrecognized_type'
            )
        );

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);
    }

    protected function invokeLoadCacheDriver(array $objectManager, ContainerBuilder $container, $cacheName)
    {
        $method = new \ReflectionMethod($this->extension, 'loadObjectManagerCacheDriver');

        $method->setAccessible(true);

        $method->invokeArgs($this->extension, array($objectManager, $container, $cacheName));
    }


    /**
     * @param array $data
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles'     => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.cache_dir'   => __DIR__,
            'kernel.debug'       => false,
            'kernel.environment' => 'test',
            'kernel.name'        => 'kernel',
            'kernel.root_dir'    => __DIR__,
        ), $data)));
    }
}
