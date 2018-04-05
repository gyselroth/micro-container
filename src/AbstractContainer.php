<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

use Closure;
use Psr\Container\ContainerInterface;

abstract class AbstractContainer implements ContainerInterface
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
        $this->config = new Config($config, $this);
        $this->parent = $parent;
        $this->service[ContainerInterface::class] = $this;
    }

    /**
     * Get parent container.
     *
     * @return ContainerInterface
     */
    public function getParent(): ?ContainerInterface
    {
        return $this->parent;
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
     * Get config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
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
