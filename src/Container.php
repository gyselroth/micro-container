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
use ReflectionClass;
use ReflectionMethod;

class Container extends AbstractContainer
{
    /**
     * Get service.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        try {
            return $this->resolve($name);
        } catch (Exception\ServiceNotFound $e) {
            return $this->autoWireClass($name);
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

        if (null !== $this->parent) {
            return $this->parent->resolve($name);
        }

        throw new Exception\ServiceNotFound("service $name was not found in service tree");
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

        if (preg_match('#^\{([^{}]+)\}$#', $class, $match)) {
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
                $type_class = $type->getName();

                if ($type_class === $name) {
                    throw new Exception\InvalidConfiguration('class '.$type_class.' can not depend on itself');
                }

                try {
                    $args[$param_name] = $this->findService($name, $type_class);
                } catch (\Exception $e) {
                    if ($param->isDefaultValueAvailable() && null === $param->getDefaultValue()) {
                        $args[$param_name] = null;
                    } else {
                        throw $e;
                    }
                }
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
