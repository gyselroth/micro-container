<?php
declare(strict_types = 1);

/**
 * Micro
 *
 * @author    Raffael Sahli <sahli@gyselroth.net>
 * @copyright Copyright (c) 2017 gyselroth GmbH (https://gyselroth.com)
 * @license   MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use \ReflectionClass;
use \Closure;
use \Micro\Container\Exception;
use \Micro\Container\AdapterAwareInterface;
use \Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * Config
     *
     * @var array
     */
    protected $config = [];


    /**
     * Service registry
     *
     * @var array
     */
    protected $service = [];


    /**
     * Registered but not initialized service registry
     *
     * @var array
     */
    protected $registry = [];


    /**
     * Create container
     *
     * @param array $config
     */
    public function __construct(Iterable $config=[])
    {
        $this->config = $config;
        $container = $this;
        $this->add(ContainerInterface::class, function() use($container){
            return $container;
        });
    }


    /**
     * Get service
     *
     * @param  string $name
     * @return mixed
     */
    public function get($name)
    {
        if($this->has($name)) {
            return $this->service[$name]['instance'];
        } else {
            if(isset($this->registry[$name])) {
                $this->service[$name]['instance'] = $this->registry[$name]->call($this);
                unset($this->registry[$name]);
                return $this->service[$name]['instance'];
            } else {
                return $this->autoWire($name);
            }
        }
    }


    /**
     * Get new instance (Do not store in container)
     *
     * @param  string $name
     * @return mixed
     */
    public function getNew(string $name)
    {
        if(isset($this->registry[$name])) {
            return $this->registry[$name]->call($this);
        } else {
            return $this->autoWire($name);
        }
    }


    /**
     * Add service
     *
     * @param  string $name
     * @param  Closure $service
     * @return Container
     */
    public function add(string $name, Closure $service): Container
    {
        if($this->has($name)) {
            throw new Exception('service '.$name.' is already registered');
        }

        $this->registry[$name] = $service;
        return $this;
    }


    /**
     * Check if service is registered
     *
     * @return bool
     */
    public function has($name): bool
    {
        return isset($this->service[$name]);
    }


    /**
     * Auto wire
     *
     * @param string $name
     * @param Iterable $config
     * @param array $parents
     * @return mixed
     */
    protected function autoWire(string $name, $config=null, array $parents=[])
    {
        if($config === null) {
            $config = $this->config;
        }

        $class = $name;
        $sub_config = $config;
        if(isset($config[$name])) {
            if(isset($config[$name]['use'])) {
                $class = $config[$name]['use'];
            } elseif(isset($config[$name]['name'])) {
                $class = $config[$name]['name'];
            }

            $config = $config[$name];
        } else {
            $config = [];
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch(\Exception $e) {
            throw new Exception($class.' can not be resolved to an existing class');
        }

        $constructor = $reflection->getConstructor();

        if($constructor === null) {
            return new $class();
        } else {
            $params = $constructor->getParameters();
            $args = [];

            foreach($params as $param) {
                $type = $param->getClass();
                $param_name = $param->getName();

                if($type === null) {
                    try {
                        $args[$param_name] = $this->getParam($name, $param_name, $sub_config);
                    } catch(Exception $e) {
                        if($param->isDefaultValueAvailable()) {
                            $args[$param_name] = $param->getDefaultValue();
                        } elseif($param->allowsNull()) {
                            $args[$param_name] = null;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $type_class = $type->getName();

                    if($type_class === $name) {
                        throw new Exception('class '.$type_class.' can not depend on itself');
                    }

                    $args[$param_name] = $this->findParentService($name, $type_class, $config, $parents);
               }
            }

            return $this->createInstance($name, $reflection, $args, $config, $parents);
        }
    }


    /**
     * Traverse services with parents and find correct service to use
     *
     * @param  string $name
     * @param  string $class
     * @return mixed
     */
    protected function findParentService(string $name, string $class, $config, $parents)
    {
        $service = null;
        $services = $this->service;
        foreach(array_reverse($parents) as $name => $parent) {
            if(isset($services[$name])) {
                $service = $services[$name];
                if(isset($services['service'])) {
                    $services = $services['service']; }
                else {
                    break;
                }
            } else {
                break;
            }
        }

        if($service !== null) {
            return $service['instance'];
        }

        foreach(array_reverse($parents) as $parent) {
            if(isset($parent['service'][$class])) {
                return $this->autoWire($class, $parent['service'], $parents);
            }
        }

        return $this->get($class);
    }


    /**
     * Create instance
     *
     * @param  string $name
     * @param  ReflectionClass $class
     * @param  array $args
     * @return mixed
     */
    protected function createInstance(string $name, ReflectionClass $class, array $args, Iterable $config, $parents=[])
    {
        $instance = $class->newInstanceArgs($args);

        $loop = &$this->service;
        foreach($parents as $p => $parent) {
            $loop = &$loop[$p];
        }
        if(count($parents) === 0) {
            $loop[$name]['instance'] = $instance;
        }   else {
            $loop['service'][$name]['instance'] = $instance;
        }


        $parents[$name] = $config;
        $parents_orig = $parents;

        array_unshift($parents, $name);

        if($instance instanceof AdapterAwareInterface) {
            if(isset($config['adapter'])) {
                $adapters = $config['adapter'];
            } else {
                $adapters = $instance->getDefaultAdapter();
            }

            foreach($adapters as $adapter => $service) {
                if(isset($service['enabled']) && $service['enabled'] === '0') {
                    continue;
                }

                $parents = $parents_orig;
                $parents[$adapter] = $service;
                $class = $adapter;
                $adapter_instance = $this->autoWire($class, $adapters, $parents);

                if(isset($service['expose']) && $service['expose']) {
                    $this->service[$adapter]['instance'] = $adapter_instance;
                }

                $instance->injectAdapter($adapter_instance, $adapter);
            }
        }

        return $instance;
    }


    /**
     * Parse param value
     *
     * @param mixed $param
     * @return mixed
     */
    protected function parseParam($param)
    {
        if(is_iterable($param)) {
            foreach($param as $key => $value) {
                $param[$key] = $this->parseParam($value);
            }

            return $param;
        } elseif(is_string($param)) {
            if(preg_match('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)(.*))\)\}#', $param, $match)) {
                if(count($match) !== 4) {
                    return $param;
                }

                $env = getenv($match[1]);
                if($env === false && !empty($match[3])) {
                    return str_replace($match[0], $match[3], $param);
                } elseif($env === false) {
                    throw new Exception('env variable '.$match[1].' required but it is neither set not a default value exists');
                } else {
                    return str_replace($match[0], $env, $param);
                }
            } else {
                return $param;
            }
        } else {
            return $param;
        }
    }




    /**
     * Get config value
     *
     * @param  string $name
     * @param  string $param
     * @return mixed
     */
    public function getParam(string $name, string $param, ?Iterable $config=null)
    {
        if($config === null) {
            $config = $this->config;
        }

        if(!isset($config[$name]['options'][$param])) {
            throw new Exception('no configuration available for required service parameter '.$param);
        }

        return $this->parseParam($config[$name]['options'][$param]);
    }
}
