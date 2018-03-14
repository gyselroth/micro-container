<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container\Testsuite;

use Micro\Container\Config;
use Micro\Container\Container;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConfigHas()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $config = new Config($config, new Container());
        $this->assertTrue($config->has(Mock\StringArguments::class));
    }

    public function testConfigGet()
    {
        $config = [
            Mock\StringArguments::class => [
                'arguments' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        $instance = new Config($config, new Container());
        $this->assertSame($config[Mock\StringArguments::class]['arguments'], $instance->get(Mock\StringArguments::class)['arguments']);
    }

    public function testConfigGetDefaults()
    {
        $config = [
            Mock\StringArguments::class => [],
        ];

        $instance = new Config($config, new Container());
        $service = $instance->get(Mock\StringArguments::class);
        $this->assertTrue($service['merge']);
        $this->assertFalse($service['singleton']);
        $this->assertFalse($service['lazy']);
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

        $instance = new Config($config, new Container());
        $service = $instance->get(Mock\StringArgumentsComplexChild::class);
        $this->assertSame('bar', $service['arguments']['foo']);
        $this->assertSame('foo', $service['arguments']['bar']);
        $this->assertSame('barfoo', $service['arguments']['foobar']);
    }

    public function testConfigServiceMergeDisabled()
    {
        $config = [
            Mock\StringArgumentsInterface::class => [
                'arguments' => [
                    'bar' => 'foobar',
                ],
            ],
            Mock\StringArgumentsComplexChild::class => [
                'arguments' => [
                    'bar' => 'foo',
                ],
                'merge' => false,
            ],
        ];

        $instance = new Config($config, new Container());
        $service = $instance->get(Mock\StringArgumentsComplexChild::class);
        $this->assertSame('foo', $service['arguments']['bar']);
    }

    public function testConfigGetEnv()
    {
        putenv('FOO=bar');

        $param = '{ENV(FOO)}';
        $instance = new Config([], new Container());
        $this->assertSame('bar', $instance->getEnv($param));
    }
}
