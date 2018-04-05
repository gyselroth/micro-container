<?php

declare(strict_types=1);

/**
 * Micro\Container
 *
 * @copyright   Copryright (c) 2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

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
}
