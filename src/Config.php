<?php

declare(strict_types=1);

/**
 * Micro
 *
 * @copyright   Copryright (c) 2015-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     MIT https://opensource.org/licenses/MIT
 */

namespace Micro\Container;

class Config
{
    /**
     * Config.
     *
     * @var iterable
     */
    protected $config = [];

    /**
     * Compiled config.
     *
     * @var array
     */
    protected $compiled = [];

    /**
     * Create container.
     *
     * @param iterable $config
     */
    public function __construct(Iterable $config = [])
    {
        $this->config = $config;
    }

    /**
     * Check if service is known to container config.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /**
     * Get service configuration.
     *
     * @param string $name
     *
     * @return array
     */
    public function get(string $name): array
    {
        if (isset($this->compiled[$name])) {
            $config = $this->compiled[$name];
        } else {
            $this->compiled[$name] = $this->createServiceConfig($name);
            $config = $this->compiled[$name];
        }

        if (isset($config['use'])) {
            if (!is_string($config['use'])) {
                throw new Exception\InvalidConfiguration('use must be a string for service '.$name);
            }
        } else {
            $config['use'] = $name;
        }

        return $config;
    }

    /**
     * Parse env param.
     *
     * @param string $param
     *
     * @return string
     */
    public function getEnv(string $param): string
    {
        if (preg_match_all('#\{ENV\(([A-Za-z0-9_]+)(?:(,?)([^}]*))\)\}#', $param, $matches)) {
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

        return $param;
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
        if ($this->has($name)) {
            $config = $this->config[$name];
        }

        $class = $name;
        if (isset($config['use'])) {
            $class = $config['use'];
        }

        if (preg_match('#^\{(.*)\}$#', $class)) {
            return $config;
        }

        $parents = array_merge(class_implements($class), class_parents($class));
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
}
