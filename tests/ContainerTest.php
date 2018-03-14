<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite;

use Micro\Container\Container;
use Micro\Container\Exception;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\ProxyInterface;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    public function testAddCallable()
    {
        $container = new Container();
        $container->add('test', function () {
            return new Mock\Simple();
        });

        $this->assertInstanceOf(Mock\Simple::class, $container->get('test'));
    }

    public function testAddStatic()
    {
        $container = new Container();
        $container->add('test', new Mock\Simple());
        $this->assertInstanceOf(Mock\Simple::class, $container->get('test'));
    }

    public function testAddStaticAlreadyExists()
    {
        $this->expectException(Exception\ServiceAlreadyExists::class);
        $container = new Container();
        $container->add('test', new Mock\Simple());
        $this->assertInstanceOf(Mock\Simple::class, $container->get('test'));
        $container->add('test', new Mock\Simple());
    }

    public function testGetNonExistNamed()
    {
        $this->expectException(Exception\ServiceNotFound::class);
        $container = new Container();
        $container->get('test');
    }

    public function testDynamicSameInstance()
    {
        $container = new Container();
        $this->assertSame($container->get(Mock\Simple::class), $container->get(Mock\Simple::class));
    }

    public function testAddWithConstructorArguments()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertSame('bar', $service->getFoo());
    }

    public function testAddWithConstructorArgumentsOnlySetOneArgument()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
        $this->assertSame('bar', $service->getBar());
        $this->assertSame('bar', $service->getFoo());
    }

    public function testAddWithConstructorArgumentsAndCall()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'method' => 'setFoo',
                        'arguments' => [
                            'foo' => 'foo',
                        ],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertSame('foo', $service->getFoo());
    }

    public function testAddWithConstructorArgumentsAndCallSetOneArgument()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'method' => 'setFoo',
                        'arguments' => [
                            'foo' => 'foo',
                        ],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
        $this->assertSame('bar', $service->getBar());
        $this->assertSame('foo', $service->getFoo());
    }

    public function testRewriteChildClass()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'use' => Mock\StringArgumentsChild::class,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertInstanceOf(Mock\StringArgumentsChild::class, $service);
    }

    public function testRewriteImplementationFromInterface()
    {
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'use' => Mock\StringArgumentsChild::class,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsInterface::class);
        $this->assertInstanceOf(Mock\StringArgumentsChild::class, $service);
    }

    public function testGetServiceWithRequiredClassDependency()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $this->assertSame('bar', $service->getFoo());
    }

    public function testGetServiceWithRequiredInterfaceDependency()
    {
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'use' => Mock\StringArgumentsChild::class,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\InterfaceDependencyRequiredArguments::class);
        $this->assertSame('bar', $service->getFoo());
    }

    public function testInvalidUseConfiguration()
    {
        $this->expectException(Exception\InvalidConfiguration::class);
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'use' => ['foo' => Mock\StringArgumentsChild::class],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsInterface::class);
    }

    public function testMissingMethodInvalidConfiguration()
    {
        $this->expectException(Exception\InvalidConfiguration::class);
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'arguments' => [
                            'foo' => 'foo',
                        ],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
    }

    public function testHas()
    {
        $config = [
            Mock\Simple::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame(false, $container->has(Mock\Simple::class));
        $service = $container->get(Mock\Simple::class);
        $this->assertSame(true, $container->has(Mock\Simple::class));
    }

    public function testHasSelf()
    {
        $container = new Container();
        $this->assertSame(true, $container->has(ContainerInterface::class));
    }

    public function testSingleton()
    {
        $config = [
            Mock\Simple::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'singleton' => true,
            ],
        ];

        $container = new Container($config);
        $this->assertNotSame($container->get(Mock\Simple::class), $container->get(Mock\Simple::class));
    }

    public function testSameDependeny()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $service2 = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $this->assertSame($service->getDependency(), $service2->getDependency());
    }

    public function testSingletonDependency()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'singleton' => true,
            ],
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'use' => Mock\StringArgumentsChild::class,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $service2 = $container->get(Mock\InterfaceDependencyRequiredArguments::class);
        $this->assertNotSame($service->getDependency(), $service2->getDependency());
    }

    public function testConfigureServicesViaInterface()
    {
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsChild::class);
        $service2 = $container->get(Mock\StringArgumentsImplementation::class);
        $this->assertSame('bar', $service->getFoo());
        $this->assertSame('bar', $service2->getFoo());
    }

    public function testHasNoParentContainer()
    {
        $container = new Container([]);
        $this->assertSame(null, $container->getParent());
    }

    public function testParentContainer()
    {
        $parent = new Container([]);
        $container = new Container([], $parent);
        $this->assertSame($parent, $container->getParent());
    }

    public function testGetLazyService()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'lazy' => true,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertInstanceOf(ProxyInterface::class, $service);
    }

    public function testTransformLazyService()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'lazy' => true,
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertInstanceOf(ProxyInterface::class, $service);
        $service = $service->getFoo();
        $this->assertSame('bar', $service);
        $this->assertNotInstanceOf(ProxyInterface::class, $service);
    }

    public function testChildService()
    {
        $config = [
            Mock\ClassDependencyRequiredArguments::class => [
                'services' => [
                    Mock\StringArguments::class => [
                        'arguments' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $this->assertSame('bar', $service->getFoo());
        $this->assertNotSame($service->getDependency(), $container->get(Mock\StringArguments::class));
    }

    public function testConfigServiceMerge()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foobar' => 'barfoo',
                ],
            ],
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
            Mock\StringArgumentsComplexChild::class => [
                'arguments' => [
                    'bar' => 'foo',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplexChild::class);
        $this->assertSame('bar', $service->getFoo());
        $this->assertSame('foo', $service->getBar());
        $this->assertSame('barfoo', $service->getFoobar());
    }

    public function testConfigGetServiceWithEnv()
    {
        putenv('FOO=bar');

        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => '{ENV(FOO)}',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get(Mock\StringArguments::class)->getFoo());
    }

    public function testConfigGetServiceWithMultipleEnv()
    {
        putenv('FOO=bar');
        putenv('BAR=foo');

        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => '{ENV(FOO)}-{ENV(BAR)}',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar-foo', $container->get(Mock\StringArguments::class)->getFoo());

        putenv('FOO');
        putenv('BAR');
    }

    public function testConfigInvalidEnv()
    {
        $this->expectException(Exception\EnvVariableNotFound::class);
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => '{ENV(FOO)}',
                ],
            ],
        ];

        $container = new Container($config);
        $container->get(Mock\StringArguments::class)->getFoo();
    }

    public function testConfigDefaultEnvValue()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => '{ENV(FOO,foobar)}',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('foobar', $container->get(Mock\StringArguments::class)->getFoo());
    }
}
