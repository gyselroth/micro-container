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
     * @var Iterable
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
     * Parent container
     *
     * @var ContainerInterface
     */
    protected $parent;

    /**
     * Children container
     *
     * @var ContainerInterface[]
     */
    protected $children = [];

    /**
     * Create container.
     *
     * @param Iterable $config
     */
    public function __construct(Iterable $config = [], ?ContainerInterface $parent=null)
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
        if ($this->has($name)) {
            return $this->service[$name];
        }

        if (isset($this->registry[$name])) {
            return $this->addStaticService($name);
        }

        if(isset($this->config[$name])) {
            return $this->autoWireClass($name);
        }

        try {
            return $this->lookupService($name);
        } catch(Exception\ServiceNotFound $e) {
            return $this->autoWireClass($name);
        }
    }

    /**
     * Check for static injections
     *
     * @param string $name
     * @return mixed
     */
    protected function addStaticService(string $name)
    {
        if( $this->registry[$name] instanceof Closure) {
            $this->service[$name] = $this->registry[$name]->call($this);
        } else {
            $this->service[$name] = $this->registry[$name];
        }

        unset($this->registry[$name]);
        return $this->service[$name];
    }

    /**
     * Traverse tree up and look for service
     *
     * @param string $name
     * @return mixed
     */
    public function lookupService(string $name)
    {
        if ($this->has($name)) {
            return $this->service[$name];
        }

        if (isset($this->registry[$name])) {
            return $this->addStaticService($name);
        }

        if(isset($this->config[$name])) {
            return $this->autoWireClass($name);
        }

        if($this->parent !== null) {
            return $this->parent->lookupService($name);
        }

        throw new Exception\ServiceNotFound("service $name was not found in service tree");
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
    protected function autoWireClass(string $name)
    {
        $class = $name;

        $config = $this->config;
        if (isset($this->config[$name])) {
            $config = $this->config[$name];
        } else {
            $config = [];
        }

        if (isset($config['use'])) {
            if(!is_string($config['use'])) {
                throw new Exception\Configuration('use must be a string for service '.$name);
            }

            $class = $config['use'];
        }

        if(preg_match('#^\{(.*)\}$#', $class, $match)) {
            $service = $this->get($match[1]);

            if(isset($this->config[$name]['selects'])) {
                $reflection = new ReflectionClass(get_class($service));

                foreach($this->config[$name]['selects'] as $select) {
                    $args = $this->autoWireMethod($name, $reflection->getMethod($select['method']), $select);
                    $service = call_user_func_array([&$service, $select['method']], $args);
                }
            }

            return $this->service[$name] = $service;
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
        return $this->createInstance($name, $reflection, $args);
    }

    /**
     * Create instance.
     *
     * @param string          $name
     * @param ReflectionClass $class
     * @param array $arguments
     *
     * @return mixed
     */
    protected function createInstance(string $name, ReflectionClass $class, array $arguments)
    {
        $instance = $class->newInstanceArgs($arguments);
        $this->service[$name] = $instance;

        if(isset($this->config[$name]['calls'])) {
            foreach($this->config[$name]['calls'] as $call) {
                if(!isset($call['method'])) {
                    throw new Exception\Configuration('method is required for setter injection in service '.$name);
                }

                $arguments = [];
                try {
                    $method = $class->getMethod($call['method']);
                } catch(\ReflectionException $e) {
                    throw new Exception\Configuration('method '.$call['method'].' is not callable in class '.$class->getName().' for service '.$name);
                }

                $arguments = $this->autoWireMethod($name, $method, $call);
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
     * @return array
     */
    protected function autoWireMethod(string $name, ReflectionMethod $method, array $config): array
    {
        $params = $method->getParameters();
        $args = [];

        foreach ($params as $param) {
            $type = $param->getClass();
            $param_name = $param->getName();

            if(isset($config['arguments'][$param_name])) {
                $args[$param_name] = $this->parseParam($config['arguments'][$param_name], $name);
            } elseif($type !== null) {
                $type_class = $type->getName();

                if ($type_class === $name) {
                    throw new Exception\Logic('class '.$type_class.' can not depend on itself');
                }

                $args[$param_name] = $this->findService($name, $type_class);
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
                return $this->findService($name, $match[1]);
            }

            return $param;
        }

        return $param;
    }


    /**
     * Locate service
     *
     * @param string $current_service
     * @param string $service
     */
    protected function findService(string $current_service, string $service)
    {
        if(isset($this->children[$current_service])) {
            return $this->children[$current_service]->get($service);
        }

        if(isset($this->config[$current_service]['services'])) {
            $this->children[$current_service] = new self($this->config[$current_service]['services'], $this);
            return $this->children[$current_service]->get($service);
        }

        return $this->get($service);
    }
}
