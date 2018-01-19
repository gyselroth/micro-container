# Micro Dependency Injection Container
[![Build Status](https://travis-ci.org/gyselroth/micro-container.svg?branch=master)](https://travis-ci.org/gyselroth/micro-container)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/micro-container/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gyselroth/micro-container/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/gyselroth/micro-container.svg)](https://packagist.org/packages/gyselroth/micro-container)
[![GitHub release](https://img.shields.io/github/release/gyselroth/micro-container.svg)](https://github.com/gyselroth/micro-container/releases)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/gyselroth/micro-container/master/LICENSE)

## Description
This is a lightweight dependency injection container for PHP 7.1+.
It supports full autowiring and lets you configure the container with whatever config system you want.
Since it does build everything on the fly (No worries reflection is cached by PHP itself) it is super fast compared to other DIC.
Of course its features are limited compared to a DIC like Symfony or PHP-DI but it will fit for most projects and the configuration is simpler and feels more lightweight.

## Requirements
The library is only >= PHP7.1 compatible.

## Download
The package is available at packagist: https://packagist.org/packages/gyselroth/micro-container

To install the package via composer execute:
```
composer require gyselroth/micro-container
```

## Documentation
We all know how a DIC must work so we go directly to a example how to use it with a common dependency such as 
a [Monolog](https://github.com/Seldaek/monolog) logger.

```php
use Micro\Container\Container; 
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;

$config = [
    LoggerInterface::class => [
        'use' => Logger::class,
        'calls' => [
            StreamHandler::class => [
                'method' => 'pushHandler',
                'arguments' => ['handler' => '{'.StreamHandler::class.'}']
            ],
        ],
        'services' => [
            StreamHandler::class => [
                'arguments' => [
                    'stream' => '/tmp/my_app.log',
                    'level' => 100
                ]
                'calls' => [
                    FormatterInterface::class => [
                        'method' => 'setFormatter'
                    ]
                ]
            ]
        ]
    ],
    FormatterInterface::class => [
        'use' => LineFormatter::class,
        'arguments' => [
            'dateFormat' => "Y-d-m H:i:s",
            'format' => "%datetime% [%context.category%,%level_name%]: %message% %context.params% %context.exception%\n"
        ]
    ]
];

$container =new Container($config);
$->get(LoggerInterface::class)->info('Hello world');
```

### Configuration
The container only accepts one argument and this is a configuration. 
Huge advantage is, that you can use anything you would like to configure your container.
You can configure it directly via PHP like the example above or use a configuration file or even a configuration library such as [Noodlehouse\Config](https://github.com/hassankhan/config).

Here is the same config but in YAML:
```yaml
Monolog\Formatter\FormatterInterface:
  use: Monolog\Formatter\LineFormatter
  arguments:
    dateFormat: "Y-d-m H:i:s"
    format: "%datetime% [%context.category%,%level_name%]: %message% %context.params% %context.exception%\n"
Psr\Log\LoggerInterface:
  use: "Monolog\\Logger"
  arguments:
    name: default
  calls:
    Monolog\Handler\StreamHandler:
      method: pushHandler
      arguments:
        handler: '{Monolog\Handler\StreamHandler}'
  services:
    Monolog\Handler\StreamHandler:
      use: \Monolog\Handler\StreamHandler
      arguments:
        stream: '{ENV(LOG_FOLDER,/tmp)}/my_app.log'
        level: 100
      calls:
        formatter:
          method: setFormatter
```

Using (for example [Noodlehouse\Config](https://github.com/hassankhan/config])) would result in:

```php
use Noodlehouse\Config;
use Micro\Container\Container;

$path = __DIR__.DIRECTORY_SEPARATOR.'*.yaml';
$config = new Config($path);
$container = new Container($config);
```

### Autowiring
This container does autowiring by default. You do not need to configure anything for it.
Also it is not required that you configure a service explicitly which is requested by a dependency. 
If it is not configured but can be resolved anyway you're good to go.

### Constructor injection
You can pass constructor arguments via `arguments`. **Attention**: The container is based on named arguments.
Order does not matter but you need to name the arguments as they are defined in the constructor of a class itself.

### Setter injection
Setter inection is done via `calls`. Again arguments must be named. Ordering doesn't matter. The container tries to resolve all arguments where it can be done
automatically. 
`calls` requires an array with setter calls. The name of a call like in this example `StreamHandler::class` does not matter, 
it is only named that it can easily overwritten by other configuration files/array.
One setter requires a `method` and optionally an array of `arguments`.
Again the container works with named arguments. If StreamHandler::pushHandler() has an argument named 'handler' you have to name it 'handler', order does not matter.

```php
'calls' => [
   StreamHandler::class => [
        'method' => 'pushHandler',
        'arguments' => ['handler' => '{'.StreamHandler::class.'}']
   ],
]
```

The same goes with an unnamed call:
```php
'calls' => [
   [
        'method' => 'pushHandler',
        'arguments' => ['handler' => '{'.StreamHandler::class.'}']
   ],
]
```

### Reference to other services
If you want to pass another service you can wrap your value into `{service name}`. This will let the container to search for a service called 'service name'.

### Interfaces
A service named with an interface name like `Psr\Log\LoggerInterface` can be configured to use specific implementation like `Monolog\Logger` via the 
keyword `use`.

### Values and environment variables
Passing values work like passing service references. The only difference is that static values must not be wrapped in `{}`.
It is also possible to read values from environment variables. This can be done like `{ENV(LOG_FOLDER)}` or with an optional default
value `{ENV(LOG_FOLDER,/tmp)}`. If the variable is found it will use the value of `LOG_FOLDER` otherwise the default value. If no default value is given and the env variable was not found an exception `Micro\Container\Exception\EnvVariableNotFound` will be thrown.

### Exposing and nesting services
Services configured at the top level of the configuration are exposed by default. You can nest services via `services` to hide services within the container.
This leads to a cleaner and more readable configuration. Given the Monolog example the services `Monolog\Formatter\FormatterInterface` and `Psr\Log\LoggerInterface` are configured at the top level.
Therefore those can be requested directly from the container whereas `Monolog\Handler\StreamHandler` is a sub service of `Psr\Log\LoggerInterface` and can not be requested.
The container tries to look up services from the bottom to the top. If there is service configured with the name the container is looking for it takes that configuration and injects the service at this level.
If no service is found the container will look a level above and so on.

### Using method result as service
It is possible to define a service which does use the result of a method call of another service. Have a look at this example where we need 
an instance of `MongoDB\Database` but this instance must be created from `MongoDB\Client`.
```php
[
    \MongoDB\Client::class => [
        'arguments' => [
            'uri' => 'mongodb://localhost:27017',
            'driverOptions' => [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ]
            ]
        ],
    ],
    MongoDB\Database::class => [
        'use' => '{MongoDB\Client}',
        'selects' => [[
            'method' => 'selectDatabase',
            'arguments' => [
                'databaseName' => 'balloon'
            ]
        ]]
    ]
]
```
Instead setting a specific class in the `use` statement you can wrap another service in `{}` to use that service as a parent service.
The service `MongoDB\Database` is now actually an instance of `MongoDB\Client`. We can use the statement `selects` to call a method on that very instance
and using the result of it as our service. `selects` also supports chaining since you need to define an array of methods anyway. Therefore if a seccond method is defined the method would be called on the result of the first selects method (And so on).
