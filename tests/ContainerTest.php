<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite;

use Closure;
use Micro\Container\Container;
use Micro\Container\Exception;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\ProxyInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ContainerTest extends TestCase
{
    public function testGetNonExistNamed()
    {
        $this->expectException(Exception\InvalidConfiguration::class);
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

    public function testAddWithConstructorArgumentsMissingArgument()
    {
        $this->expectException(Exception\InvalidConfiguration::class);
        $container = new Container();
        $service = $container->get(Mock\StringArgumentsComplex::class);
    }

    public function testSetArrayOfArguments()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'bar' => 'foo',
                ],
                'calls' => [[
                    'method' => 'setFooBar',
                    'arguments' => [
                        'foobar' => [
                            'foo' => 'foo',
                            'bar' => 'foo',
                        ],
                    ],
                ]],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
        $this->assertSame('foo', $service->getBar());
        $this->assertSame('foo', $service->getFoo());
    }

    public function testAddWithConstructorArgumentsOnlySetOneArgument()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'bar' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
        $this->assertSame('bar', $service->getBar());
        $this->assertSame('bar', $service->getFoo());
    }

    public function testConstructorArgumentsOrderDoesNotMatter()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                    'bar' => 'foo',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplex::class);
        $this->assertSame($service->getFoo(), 'bar');
        $this->assertSame($service->getBar(), 'foo');
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

    public function testSkipNonArrayCalls()
    {
        $config = [
            Mock\StringArguments::class => [
                'calls' => [
                    null,
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

    public function testSetterBoolInjectionAllowedNullValue()
    {
        $config = [
            Mock\StringArguments::class => [
                'calls' => [
                    [
                        'method' => 'setBar',
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArguments::class);
        $this->assertSame(null, $service->getBar());
    }

    public function testAddWithConstructorArgumentsAndCallSetOneArgument()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'bar' => 'bar',
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

    public function testGetServiceWithOptionalNotExistingClassDependency()
    {
        $config = [
            Mock\StringArguments::class => [
                'use' => 'foo',
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyOptionalArguments::class);
        $this->assertSame(null, $service->getDependency());
    }

    public function testGetServiceWithRequiredNotExistingClassDependency()
    {
        $this->expectException(Exception\InvalidConfiguration::class);

        $config = [
            Mock\StringArguments::class => [
                'use' => 'foo',
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
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

    public function testInvalidCallMethod()
    {
        $this->expectException(Exception\InvalidConfiguration::class);
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'method' => 'foo',
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
        $this->assertSame(true, $container->has(Mock\Simple::class));
        $service = $container->get(Mock\Simple::class);
        $this->assertSame(true, $container->has(Mock\Simple::class));
    }

    public function testHasNot()
    {
        $container = new Container([]);
        $this->assertSame(false, $container->has('foobar'));
    }

    public function testHasSelf()
    {
        $container = new Container();
        $this->assertSame(true, $container->has(ContainerInterface::class));
    }

    public function testSelfIsSelf()
    {
        $container = new Container();
        $this->assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testSingleton()
    {
        $config = [
            Mock\Simple::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'singleton' => false,
            ],
        ];

        $container = new Container($config);
        $this->assertNotSame($container->get(Mock\Simple::class), $container->get(Mock\Simple::class));
    }

    public function testMakeInstanceArgsOnRuntime()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $instance = $container->make(Mock\StringArguments::class, ['foo' => 'foobar']);

        $this->assertSame('foobar', $instance->getFoo());
    }

    public function testMakeInstanceArgsOnRuntimeMixed()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $instance = $container->make(Mock\StringArgumentsComplex::class, ['bar' => 'foobar']);

        $this->assertSame('bar', $instance->getFoo());
        $this->assertSame('foobar', $instance->getBar());
    }

    public function testMakeInstanceArgsOnRuntimeMixedNotSameInstance()
    {
        $config = [
            Mock\StringArgumentsComplex::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $instance1 = $container->make(Mock\StringArgumentsComplex::class, ['bar' => 'foobar']);
        $instance2 = $container->make(Mock\StringArgumentsComplex::class, ['bar' => 'barfoo']);

        $this->assertNotSame($instance1, $instance2);
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

    public function testChildServiceUsingParentService()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
            Mock\ClassDependencyRequiredArguments::class => [
                'calls' => [
                    [
                        'method' => 'setBar',
                    ],
                ],
                'services' => [
                    Mock\ClassDependencyOptionalArguments::class => [
                        'calls' => [[
                            'method' => 'setFoo',
                        ]],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\ClassDependencyRequiredArguments::class);
        $this->assertSame('bar', $service->getBar()->getFoo());
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

    public function testConfigServiceMergeDisabled()
    {
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'bar' => 'foo',
                ],
            ],
            Mock\StringArgumentsComplexChild::class => [
                'merge' => false,
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $service = $container->get(Mock\StringArgumentsComplexChild::class);
        $this->assertSame('bar', $service->getBar());
    }

    public function testConfigUseWithEnv()
    {
        putenv('FOO='.Mock\StringArguments::class);

        $config = [
            'bar' => [
                'use' => '{ENV(FOO)}',
                'arguments' => [
                    'foo' => 'bar'
                ]
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get('bar')->getFoo());
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

    public function testServiceReferenceArgument()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArguments::class,
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
            Mock\ClassDependencyRequiredArguments::class => [
                'arguments' => [
                    'foo' => '{bar}',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get(Mock\ClassDependencyRequiredArguments::class)->getFoo());
    }

    public function testArgumentEscaped()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => '{{test}}',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('{test}', $container->get(Mock\StringArguments::class)->getFoo());
    }

    public function testServiceWhichUsesMethodResultInCallsDefintion()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'method' => 'getFoo',
                        'select' => true,
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get(Mock\StringArguments::class));
    }

    public function testServiceWhichUsesChainedMethodResult()
    {
        $config = [
            Mock\ClassDependencyRequiredArguments::class => [
                'calls' => [
                    [
                        'method' => 'getDependency',
                        'select' => true,
                    ],
                    [
                        'method' => 'getFoo',
                        'select' => true,
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('foo', $container->get(Mock\ClassDependencyRequiredArguments::class));
    }

    public function testServiceUseReference()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArguments::class,
            ],
            'foo' => [
                'use' => '{bar}',
            ],
        ];

        $container = new Container($config);
        $this->assertSame($container->get('foo'), $container->get('bar'));
    }

    public function testFactory()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArguments::class,
                'factory' => 'factory',
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get('bar')->getFoo());
    }

    public function testFactorySubCall()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArguments::class,
                'factory' => 'factory',
                'arguments' => [
                    'foo' => 'bar',
                ],
                'calls' => [
                    [
                        'method' => 'setFoo',
                        'arguments' => ['foo' => 'foofoo'],
                    ],
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('foofoo', $container->get('bar')->getFoo());
    }

    public function testFactorySeparateClass()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArgumentsFactory::class,
                'factory' => 'build',
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('bar', $container->get('bar')->getFoo());
    }

    public function testWrapCallback()
    {
        $config = [
            'bar' => [
                'use' => Mock\StringArguments::class,
                'arguments' => [
                    'foo' => 'bar',
                ],
                'wrap' => true,
            ],
        ];

        $container = new Container($config);
        $this->assertInstanceOf(Closure::class, $container->get('bar'));
        $this->assertSame('bar', $container->get('bar')()->getFoo());
    }

    public function testNonStringConstructor()
    {
        $config = [
            Mock\IntArguments::class => [
                'arguments' => [
                    'foo' => 1,
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame(1, $container->get(Mock\IntArguments::class)->getFoo());
    }

    public function testPassByReferenceDefaultValue()
    {
        $container = new Container();
        $this->assertSame('bar', $container->get(Mock\ClassReferenceArgument::class)->getFoo());
    }

    public function testPassByReferenceValue()
    {
        $config = [
            Mock\ClassReferenceArgument::class => [
                'arguments' => [
                    'foo' => 'foo',
                ],
            ],
        ];

        $container = new Container($config);
        $this->assertSame('foo', $container->get(Mock\ClassReferenceArgument::class)->getFoo());
    }

    public function testDependencyBySelf()
    {
        $this->expectException(RuntimeException::class);
        $container = new Container([]);
        $service = $container->get(Mock\ClassDependencySelf::class);
    }
}
