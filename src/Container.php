<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Micro\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Micro\Container\Exception;

class Container implements ContainerInterface
{
    /**
     * Config.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Service registry.
     *
     * @var array
     */
    protected $service = [];

    /**
     * Registered but not initialized service registry.
     *
     * @var array
     */
    protected $registry = [];

    /**
     * Create container.
     *
     * @param Iterable $config
     */
    public function __construct(Iterable $config = [])
    {
        $this->config = $config;
        $this->add(ContainerInterface::class, $this);
    }

    /**
     * Get service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        if ($this->has($name)) {
            return $this->service[$name]['instance'];
        }
        if (isset($this->registry[$name])) {
            if( $this->registry[$name] instanceof Closure) {
                $this->service[$name]['instance'] = $this->registry[$name]->call($this);
            } else {
                $this->service[$name]['instance'] = $this->registry[$name];
            }

            unset($this->registry[$name]);
            return $this->service[$name]['instance'];
        }

        return $this->autoWireClass($name);
    }


    /**
     * Debug container service tree
     *
     * @return array
     */
    public function __debug(?array $container=null): array
    {
        if($container === null) {
            $container = $this->service;
        }

        foreach($container as $name => &$service) {
            if(isset($service['instance'])) {
                $service['instance'] = 'instanceof '.get_class($service['instance']);
            }

            if(isset($service['services'])) {
                $service['services'] = $this->__debug($service['services']);
            }
        }

        return $container;
    }


    /**
     * Get new instance (Do not store in container).
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getNew(string $name)
    {
        if (isset($this->registry[$name])) {
            return $this->registry[$name]->call($this);
        }

        return $this->autoWireClass($name);
    }

    /**
     * Add service.
     *
     * @param string $name
     * @param mixed $service
     *
     * @return Container
     */
    public function add(string $name, $service): self
    {
        if ($this->has($name)) {
            throw new Exception\ServiceAlreadyExists('service '.$name.' is already registered');
        }

        $this->registry[$name] = $service;

        return $this;
    }

    /**
     * Check if service is registered.
     *
     * @param mixed $name
     *
     * @return bool
     */
    public function has($name): bool
    {
        return isset($this->service[$name]);
    }

    /**
     * Auto wire.
     *
     * @param string   $name
     * @param array $config
     * @param array $parents
     *
     * @return mixed
     */
    protected function autoWireClass(string $name, ?array $config = null, array $parents = [])
    {
        if (null === $config) {
            $config = $this->config;
        }

        $class = $name;
        $sub_config = $config;
        if (isset($config[$name])) {
            if (isset($config[$name]['use'])) {
                $class = $config[$name]['use'];
            }

            $config = $config[$name];
        } else {
            $config = [];
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (\Exception $e) {
            throw new Exception\Configuration($class.' can not be resolved to an existing class for service '.$name);
        }

        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return new $class();
        }

        $args = $this->autoWireMethod($name, $constructor, $config, $parents);
        return $this->createInstance($name, $reflection, $args, $config, $parents);
    }

    /**
     * Traverse services with parents and find correct service to use.
     *
     * @param string $name
     * @param string $class
     * @param mixed $config
     * @param mixed $parents
     *
     * @return mixed
     */
    protected function findParentService(string $name, ?string $class, $config, $parents)
    {
        $service = null;
        $services = $this->service;

        foreach (array_reverse($parents) as $name => $parent) {
            if (isset($services[$name])) {
                $service = $services[$name];
                if (isset($services['services'])) {
                    $services = $services['services'];
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        foreach (array_reverse($parents) as $parent) {
            if (isset($parent['services'][$class])) {
                return $this->autoWireClass($class, $parent['services'], $parents);
            }
        }

        return $this->get($class);
    }

    /**
     * Create instance.
     *
     * @param string          $name
     * @param ReflectionClass $class
     * @param array           $args
     * @param mixed           $parents
     *
     * @return mixed
     */
    protected function createInstance(string $name, ReflectionClass $class, array $args, array $config, $parents = [])
    {
        $instance = $class->newInstanceArgs($args);

        $loop = &$this->service;
        foreach ($parents as $p => $parent) {
            $loop = &$loop[$p];
        }

        if (0 === count($parents)) {
            $loop[$name]['instance'] = $instance;
        } else {
            $loop['services'][$name]['instance'] = $instance;
        }

        $parents[$name] = $config;
        $parents_orig = $parents;

        if(isset($config['calls'])) {
            foreach($config['calls'] as $call) {
                $arguments = [];
                try {
                    $method = $class->getMethod($call['method']);
                } catch(\ReflectionException $e) {
                    throw new Exception\Configuration('method '.$call['method'].' is not callable in class '.$class->getName().' for service '.$name);
                }

                $arguments = $this->autoWireMethod($name, $method, $call, $parents);
                call_user_func_array([&$instance, $call['method']], $arguments);
            }
        }

        return $instance;
    }

    /**
     * Autowire method
     *
     * @param string $name
     * @param ReflectionMethod $method
     * @param array $config
     * @param mixed $parents
     * @return array
     */
    protected function autoWireMethod(string $name, ReflectionMethod $method, array $config, $parents): array
    {
        $params = $method->getParameters();
        $args = [];

        foreach ($params as $param) {
            $type = $param->getClass();
            $param_name = $param->getName();

            if(isset($config['arguments'][$param_name])) {
                $args[$param_name] = $this->parseParam($config['arguments'][$param_name], $name, $type, $config, $parents);
            } elseif($type !== null) {
                $type_class = $type->getName();

                if ($type_class === $name) {
                    throw new Exception\Logic('class '.$type_class.' can not depend on itself');
                }

                $args[$param_name] = $this->findParentService($name, $type_class, $config, $parents);
            } elseif($param->isDefaultValueAvailable()) {
                 $args[$param_name] = $param->getDefaultValue();
            } elseif($param->allowsNull() && $param->hasType()) {
                 $args[$param_name] = null;
            } else {
                throw new Exception\Configuration('no value found for argument '.$param_name.' in method '.$method->getName().' for service '.$name);
            }
        }

        return $args;
    }


    /**
     * Parse param value.
     *
     * @param mixed $param
     * @param string $name
     * @param string $type_class
     * @param array $config
     * @param mixed $parents
     *
     * @return mixed
     */
    protected function parseParam($param, string $name, $type_class, array $config, $parents)
    {
        if (is_iterable($param)) {
            foreach ($param as $key => $value) {
                $param[$key] = $this->parseParam($value, $name, $type_class, $config, $parents);
            }

            return $param;
        }
        if (is_string($param)) {
            if (preg_match('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)(.*))\)\}#', $param, $match)) {
                if (4 !== count($match)) {
                    return $param;
                }

                $env = getenv($match[1]);
                if (false === $env && !empty($match[3])) {
                    return str_replace($match[0], $match[3], $param);
                }
                if (false === $env) {
                    throw new Exception\EnvVariableNotFound('env variable '.$match[1].' required but it is neither set not a default value exists');
                }

                return str_replace($match[0], $env, $param);
            } elseif(preg_match('#^\{(.*)\}$#', $param, $match)) {
                return $this->findParentService($match[1], $match[1], $config, $parents);
            }

            return $param;
        }

        return $param;
    }
}
