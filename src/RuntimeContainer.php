<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

class RuntimeContainer
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
     * @param iterable                            $config
     * @param ContainerInterface|RuntimeContainer $parent
     */
    public function __construct(Iterable $config = [], $parent = null)
    {
        $this->config = new Config($config, $this);
        $this->parent = $parent;
        $this->service[ContainerInterface::class] = $this;
    }

    /**
     * Get parent container.
     *
     * @return ContainerInterface|RuntimeContainer
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set parent service on container.
     *
     * @param mixed $service
     *
     * @return ContainerInterface|RuntimeContainer
     */
    public function setParentService($service)
    {
        $this->parent_service = $service;

        return $this;
    }

    /**
     * Get config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        try {
            return $this->resolve($name);
        } catch (Exception\ServiceNotFound $e) {
            return $this->wrapService($name);
        }
    }

    /**
     * Resolve service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function resolve(string $name)
    {
        if (isset($this->service[$name])) {
            return $this->service[$name];
        }

        if ($this->config->has($name)) {
            return $this->wrapService($name);
        }

        if (null !== $this->parent_service) {
            $parents = array_merge([$name], class_implements($this->parent_service), class_parents($this->parent_service));

            if (in_array($name, $parents, true) && $this->parent_service instanceof $name) {
                return $this->parent_service;
            }
        }

        if (null !== $this->parent) {
            return $this->parent->resolve($name);
        }

        throw new Exception\ServiceNotFound("service $name was not found in service tree");
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
        if (true === $config['singleton']) {
            return $service;
        }
        $this->service[$name] = $service;

        if (isset($this->children[$name])) {
            $this->children[$name]->setParentService($service);
        }

        return $service;
    }

    /**
     * Wrap resolved service in callable if enabled.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function wrapService(string $name)
    {
        $config = $this->config->get($name);
        if (true === $config['wrap']) {
            $that = $this;

            return function () use ($that, $name) {
                return $that->autoWireClass($name);
            };
        }

        return $this->autoWireClass($name);
    }

    /**
     * Auto wire.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function autoWireClass(string $name)
    {
        $config = $this->config->get($name);
        $class = $config['use'];

        if (preg_match('#^\{([^{}]+)\}$#', $class, $match)) {
            return $this->wireReference($name, $match[1], $config);
        }

        if (isset($config['factory'])) {
            if (!isset($config['factory']['method'])) {
                throw new Exception\InvalidConfiguration('method is required for factory in service '.$name);
            }

            if (isset($config['factory']['use'])) {
                $class = $config['factory']['use'];
            }

            $reflection = new ReflectionClass($class);
            $factory = $reflection->getMethod($config['factory']['method']);
            $args = $this->autoWireMethod($name, $factory, $config['factory']);
            $instance = call_user_func_array([$class, $config['factory']['method']], $args);

            return $this->prepareService($name, $instance, $reflection, $config);
        }

        $reflection = new ReflectionClass($class);
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
        $reflection = new ReflectionClass(get_class($service));
        $config = $this->config->get($name);
        $service = $this->prepareService($name, $service, $reflection, $config);

        return $service;
    }

    /**
     * Get instance (virtual or real instance).
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
        if (true === $config['lazy']) {
            return $this->getProxyInstance($name, $class, $arguments, $config);
        }

        return $this->getRealInstance($name, $class, $arguments, $config);
    }

    /**
     * Create proxy instance.
     *
     * @param string          $name
     * @param ReflectionClass $class
     * @param array           $arguments
     * @param array           $config
     *
     * @return mixed
     */
    protected function getProxyInstance(string $name, ReflectionClass $class, array $arguments, array $config)
    {
        $factory = new LazyLoadingValueHolderFactory();
        $that = $this;

        return $factory->createProxy(
            $class->getName(),
            function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($that, $name,$class,$arguments,$config) {
                $wrappedObject = $that->getRealInstance($name, $class, $arguments, $config);
                $initializer = null;
            }
        );
    }

    /**
     * Create real instance.
     *
     * @param string          $name
     * @param ReflectionClass $class
     * @param array           $arguments
     * @param array           $config
     *
     * @return mixed
     */
    protected function getRealInstance(string $name, ReflectionClass $class, array $arguments, array $config)
    {
        $instance = $class->newInstanceArgs($arguments);
        $instance = $this->prepareService($name, $instance, $class, $config);

        return $instance;
    }

    /**
     * Prepare service (execute sub selects and excute setter injections).
     *
     * @param string          $name
     * @param mixed           $service
     * @param ReflectionClass $class
     * @param array           $config
     *
     * @return mixed
     */
    protected function prepareService(string $name, $service, ReflectionClass $class, array $config)
    {
        foreach ($config['selects'] as $select) {
            $args = $this->autoWireMethod($name, $class->getMethod($select['method']), $select);
            $service = call_user_func_array([&$service, $select['method']], $args);
        }

        $this->storeService($name, $config, $service);

        foreach ($config['calls'] as $call) {
            if (null === $call) {
                continue;
            }

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
            call_user_func_array([&$service, $call['method']], $arguments);
        }

        return $service;
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
                $args[$param_name] = $this->resolveServiceArgument($name, $type, $param);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param_name] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[$param_name] = null;
            } else {
                throw new Exception\InvalidConfiguration('no value found for argument '.$param_name.' in method '.$method->getName().' for service '.$name);
            }
        }

        return $args;
    }

    /**
     * Resolve service argument.
     *
     * @param string              $name
     * @param ReflectionClass     $type
     * @param ReflectionParameter $param
     *
     * @return mixed
     */
    protected function resolveServiceArgument(string $name, ReflectionClass $type, ReflectionParameter $param)
    {
        $type_class = $type->getName();

        if ($type_class === $name) {
            throw new RuntimeException('class '.$type_class.' can not depend on itself');
        }

        try {
            return $this->traverseTree($name, $type_class);
        } catch (\Exception $e) {
            if ($param->isDefaultValueAvailable() && null === $param->getDefaultValue()) {
                return null;
            }

            throw $e;
        }
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

            if (preg_match('#^\{\{([^{}]+)\}\}$#', $param, $matches)) {
                return '{'.$matches[1].'}';
            }
            if (preg_match('#^\{([^{}]+)\}$#', $param, $matches)) {
                return $this->traverseTree($name, $matches[1]);
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
    protected function traverseTree(string $current_service, string $service)
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