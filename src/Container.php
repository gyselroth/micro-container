<?php

declare(strict_types=1);

/**
 * Micro
 *
 * @copyright   Copryright (c) 2015-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;

class Container implements ContainerInterface
{
    /**
     * Config.
     *
     * @var iterable
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
     * Parent container.
     *
     * @var ContainerInterface
     */
    protected $parent;

    /**
     * Children container.
     *
     * @var ContainerInterface[]
     */
    protected $children = [];

    /**
     * Create container.
     *
     * @param iterable $config
     */
    public function __construct(Iterable $config = [], ?ContainerInterface $parent = null)
    {
        $this->config = $config;
        $this->parent = $parent;
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
        if ($service = null !== $this->resolve($name)) {
            return $service;
        }

        try {
            return $this->lookupService($name);
        } catch (Exception\ServiceNotFound $e) {
            return $this->autoWireClass($name);
        }
    }

    /**
     * Traverse tree up and look for service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function lookupService(string $name)
    {
        if ($service = null !== $this->resolve($name)) {
            return $service;
        }

        if (null !== $this->parent) {
            return $this->parent->lookupService($name);
        }

        throw new Exception\ServiceNotFound("service $name was not found in service tree");
    }

    /**
     * Add service.
     *
     * @param string $name
     * @param mixed  $service
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
     * Resolve service.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function resolve(string $name)
    {
        if ($this->has($name)) {
            return $this->service[$name];
        }

        if (isset($this->registry[$name])) {
            return $this->addStaticService($name);
        }

        if (isset($this->config[$name])) {
            return $this->autoWireClass($name);
        }

        return null;
    }

    /**
     * Check for static injections.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function addStaticService(string $name)
    {
        if ($this->registry[$name] instanceof Closure) {
            $this->service[$name] = $this->registry[$name]->call($this);
        } else {
            $this->service[$name] = $this->registry[$name];
        }

        unset($this->registry[$name]);

        return $this->service[$name];
    }

    /**
     * Create service config.
     *
     * @param string $name
     *
     * @return array
     */
    protected function createServiceConfig(string $name): array
    {
        $config = [];
        $parents = array_merge(class_implements($name), class_parents($name));
        foreach ($parents as $parent) {
            if (isset($this->config[$parent])) {
                $config = array_merge($config, $this->config[$parent]);
            }
        }

        if (isset($this->config[$name])) {
            $config = array_merge($config, $this->config[$name]);
        }

        return $config;
    }

    /**
     * Auto wire.
     *
     * @param string $name
     * @param array  $config
     * @param array  $parents
     *
     * @return mixed
     */
    protected function autoWireClass(string $name)
    {
        $class = $name;
        $config = [];

        if (isset($this->config[$name])) {
            $config = $this->config[$name];
        }

        if (isset($config['use'])) {
            if (!is_string($config['use'])) {
                throw new Exception\InvalidConfiguration('use must be a string for service '.$name);
            }

            $class = $config['use'];
        } else {
            $config = $this->createServiceConfig($name);
        }

        if (preg_match('#^\{(.*)\}$#', $class, $match)) {
            $service = $this->get($match[1]);

            if (isset($this->config[$name]['selects'])) {
                $reflection = new ReflectionClass(get_class($service));

                foreach ($this->config[$name]['selects'] as $select) {
                    $args = $this->autoWireMethod($name, $reflection->getMethod($select['method']), $select);
                    $service = call_user_func_array([&$service, $select['method']], $args);
                }
            }

            return $this->storeService($name, $config, $service);
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (\Exception $e) {
            throw new Exception\ServiceNotFound($class.' can not be resolved to an existing class for service '.$name);
        }

        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return new $class();
        }

        $args = $this->autoWireMethod($name, $constructor, $config);

        return $this->createInstance($name, $reflection, $args, $config);
    }

    /**
     * Store service.
     *
     * @param param string $name
     * @param array        $config
     * @param mixed        $service
     *
     * @return mixed
     */
    protected function storeService(string $name, array $config, $service)
    {
        if (isset($config['singleton']) && true === $config['singleton']) {
            return $service;
        }

        $this->service[$name] = $service;

        return $service;
    }

    /**
     * Create instance.
     *
     * @param string          $name
     * @param ReflectionClass $class
     * @param array           $arguments
     * @param array           $config
     *
     * @return mixed
     */
    protected function createInstance(string $name, ReflectionClass $class, array $arguments, array $config)
    {
        $instance = $class->newInstanceArgs($arguments);
        $this->storeService($name, $config, $instance);

        if (!isset($this->config[$name]['calls'])) {
            return $instance;
        }

        foreach ($this->config[$name]['calls'] as $call) {
            if (!isset($call['method'])) {
                throw new Exception\InvalidConfiguration('method is required for setter injection in service '.$name);
            }

            $arguments = [];

            try {
                $method = $class->getMethod($call['method']);
            } catch (\ReflectionException $e) {
                throw new Exception\InvalidConfiguration('method '.$call['method'].' is not callable in class '.$class->getName().' for service '.$name);
            }

            $arguments = $this->autoWireMethod($name, $method, $call);
            call_user_func_array([&$instance, $call['method']], $arguments);
        }

        return $instance;
    }

    /**
     * Autowire method.
     *
     * @param string           $name
     * @param ReflectionMethod $method
     * @param array            $config
     *
     * @return array
     */
    protected function autoWireMethod(string $name, ReflectionMethod $method, array $config): array
    {
        $params = $method->getParameters();
        $args = [];

        foreach ($params as $param) {
            $type = $param->getClass();
            $param_name = $param->getName();

            if (isset($config['arguments'][$param_name])) {
                $args[$param_name] = $this->parseParam($config['arguments'][$param_name], $name);
            } elseif (null !== $type) {
                $type_class = $type->getName();

                if ($type_class === $name) {
                    throw new Exception\InvalidConfiguration('class '.$type_class.' can not depend on itself');
                }

                $args[$param_name] = $this->findService($name, $type_class);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param_name] = $param->getDefaultValue();
            } elseif ($param->allowsNull() && $param->hasType()) {
                $args[$param_name] = null;
            } else {
                throw new Exception\InvalidConfiguration('no value found for argument '.$param_name.' in method '.$method->getName().' for service '.$name);
            }
        }

        return $args;
    }

    /**
     * Parse param value.
     *
     * @param mixed  $param
     * @param string $name
     *
     * @return mixed
     */
    protected function parseParam($param, string $name)
    {
        if (is_iterable($param)) {
            foreach ($param as $key => $value) {
                $param[$key] = $this->parseParam($value, $name);
            }

            return $param;
        }
        if (is_string($param)) {
            if ($found = preg_match_all('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)([^}]*))\)\}#', $param, $matches)) {
                if (4 !== count($matches)) {
                    return $param;
                }

                for ($i = 0; $i < 1; ++$i) {
                    $env = getenv($matches[1][$i]);
                    if (false === $env && !empty($matches[3][$i])) {
                        $param = str_replace($matches[0][$i], $matches[3][$i], $param);
                    } elseif (false === $env) {
                        throw new Exception\EnvVariableNotFound('env variable '.$matches[1][$i].' required but it is neither set not a default value exists');
                    } else {
                        $param = str_replace($matches[0][$i], $env, $param);
                    }
                }

                return $param;
            }

            if (preg_match('#^\{(.*)\}$#', $param, $matches)) {
                return $this->findService($name, $matches[1]);
            }

            return $param;
        }

        return $param;
    }

    /**
     * Locate service.
     *
     * @param string $current_service
     * @param string $service
     */
    protected function findService(string $current_service, string $service)
    {
        if (isset($this->children[$current_service])) {
            return $this->children[$current_service]->get($service);
        }

        if (isset($this->config[$current_service]['services'])) {
            $this->children[$current_service] = new self($this->config[$current_service]['services'], $this);

            return $this->children[$current_service]->get($service);
        }

        return $this->get($service);
    }
}
