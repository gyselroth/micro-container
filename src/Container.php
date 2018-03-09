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
     * @var Config
     */
    protected $config;

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
     * Parent service.
     *
     * @var mixed
     */
    protected $parent_service;

    /**
     * Create container.
     *
     * @param iterable           $config
     * @param ContainerInterface $parent
     */
    public function __construct(Iterable $config = [], ?ContainerInterface $parent = null)
    {
        $this->config = new Config($config);
        $this->parent = $parent;
        $this->service[ContainerInterface::class] = $this;
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
        $service = $this->resolve($name);
        if (null !== $service) {
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
        $service = $this->resolve($name);
        if (null !== $service) {
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
     * Set parent service on container
     * (Used internally, there is no point to call this method directly).
     *
     * @param mixed $service
     *
     * @return ContainerInterface
     */
    public function setParentService($service): ContainerInterface
    {
        $this->parent_service = $service;

        return $this;
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

        if ($this->config->has($name)) {
            return $this->autoWireClass($name);
        }

        if (null !== $this->parent_service) {
            $parents = array_merge([$name], class_implements($this->parent_service), class_parents($this->parent_service));

            if (in_array($name, $parents, true) && $this->parent_service instanceof $name) {
                return $this->parent_service;
            }
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
        $config = $this->config->get($name);
        $class = $config['use'];

        if (preg_match('#^\{(.*)\}$#', $class, $match)) {
            return $this->wireReference($name, $match[1], $config);
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (\Exception $e) {
            throw new Exception\ServiceNotFound($class.' can not be resolved to an existing class for service '.$name);
        }

        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return $this->storeService($name, $config, new $class());
        }

        $args = $this->autoWireMethod($name, $constructor, $config);

        return $this->createInstance($name, $reflection, $args, $config);
    }

    /**
     * Wire named referenced service.
     *
     * @param string $name
     * @param string $refrence
     * @param array  $config
     *
     * @return mixed
     */
    protected function wireReference(string $name, string $reference, array $config)
    {
        $service = $this->get($reference);

        if (isset($config['selects'])) {
            $reflection = new ReflectionClass(get_class($service));

            foreach ($config['selects'] as $select) {
                $args = $this->autoWireMethod($name, $reflection->getMethod($select['method']), $select);
                $service = call_user_func_array([&$service, $select['method']], $args);
            }
        }

        return $this->storeService($name, $config, $service);
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

        if (isset($this->children[$name])) {
            $this->children[$name]->setParentService($service);
        }

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
        $config = $this->config->get($name);

        if (!isset($config['calls'])) {
            return $instance;
        }

        foreach ($config['calls'] as $call) {
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
            $param = $this->config->getEnv($param);

            if (preg_match('#^\{\{(.*)\}\}$#', $param, $matches)) {
                return '{'.$matches[1].'}';
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

        $config = $this->config->get($current_service);
        if (isset($config['services'])) {
            $this->children[$current_service] = new self($config['services'], $this);

            return $this->children[$current_service]->get($service);
        }

        return $this->get($service);
    }
}
