# Dependency Injection Container
[![Build Status](https://travis-ci.org/gyselroth/micro-container.svg?branch=master)](https://travis-ci.org/gyselroth/micro-container)
[![Code Coverage](https://scrutinizer-ci.com/g/gyselroth/micro-container/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/gyselroth/micro-container/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/micro-container/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gyselroth/micro-container/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/gyselroth/micro-container.svg)](https://packagist.org/packages/gyselroth/micro-container)
[![GitHub release](https://img.shields.io/github/release/gyselroth/micro-container.svg)](https://github.com/gyselroth/micro-container/releases)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/gyselroth/micro-container/master/LICENSE)

This is a lightweight dependency injection container for PHP 7.1+.
It supports full autowiring and lets you configure the container with whatever configuration format you want.
Since it is written to be lightweight and does build everything on the fly (No worries reflection is cached by PHP itself) it is super easy to use and still very fast. Despite the fact of being lightweight it still offers enough features to use it in a major project.

# Table of Contents
  * [Description](#description)
  * [Features](#features)
  * [Requirements](#requirements)
  * [Download](#download)
  * [Changelog](#changelog)
  * [Contribute](#contribute)
  * [Documentation](#documentation)
    * [Configuration](#configuration)
    * [Retrieving services](#retrieving-services)
    * [Autowiring](#autowiring)
    * [Constructor injection](#constructor-injection)
    * [Setter injection](#setter-injection)
    * [Reference to other services](#reference-to-other-services)
    * [Using Interfaces, abstract/parent classes or aliases](#using-interfaces-abstractparent-classes-or-aliases)
    * [Values and environment variables](#values-and-environment-variables)
    * [Singletons](#singletons)
    * [Factories](#factories)
    * [Lazy services](#lazy-services)
    * [Lazy services wrapped in callbacks](#lazy-services-wrapped-in-callbacks)
    * [Exposing and nesting services](#exposing-and-nesting-services)
    * [Configuring services via parent classes or interfaces](#configuring-services-via-parent-classes-or-interfaces)
    * [Using method result as service](#using-method-result-as-service)
    * [Make at runtime](#make-at-runtime)

## Features

* PSR-11 compatible DIC
* Full inbuilt autowiring
* Configurable via native php array (or anything else decoded into an array)
* Setter/Constructor injection
* Environment variables
* Lazy loading/Callback wrapping
* Singletons
* Factories
* Configuration of multiple services via interface/parent class declarations
* Supports parent container

## Requirements
The library is only >= PHP 7.1 compatible.

## Download
The package is available at packagist: https://packagist.org/packages/gyselroth/micro-container

To install the package via composer execute:
```
composer require gyselroth/micro-container
```

## Changelog
A changelog is available [here](https://github.com/gyselroth/micro-container/blob/master/CHANGELOG.md).

## Contribute
We are glad that you would like to contribute to this project. Please follow the given [terms](https://github.com/gyselroth/micro-container/blob/master/CONTRIBUTING.md).

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
                    'stream' => '{ENV(LOG_FOLDER),/tmp}/my_app.log',
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

$container = new Container($config);
$->get(LoggerInterface::class)->info('Hello world');
```

### Configuration
The container only accepts one argument and this is a configuration. 
Huge advantage is, that you can use anything you would like to configure your container.
You can configure it directly via PHP like the example above or use a configuration file or even a configuration library such as [Noodlehaus\Config](https://github.com/hassankhan/config).

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

Using (for example [Noodlehaus\Config](https://github.com/hassankhan/config)) would result in:

```php
use Noodlehaus\Config;
use Micro\Container\Container;

$path = __DIR__.DIRECTORY_SEPARATOR.'*.yaml';
$config = new Config($path);
$container = new Container($config);
```

## Retrieving services
Retrieve as service from the container is an easy task, let us
request the logger:

```php
$container = new Container();
$container->get(LoggerInterface::class)->info('Hello world');
```

### Autowiring
This container does autowiring by default. You do not need to configure anything for it.
Also it is not required that you configure a service explicitly which is requested by a dependency. 
If it is not configured but can be resolved anyway you're good to go.
The container tries to resolve everything possible automatically. You only need to configure what is really required.

### Constructor injection
You can pass constructor arguments via the keyword `arguments`.

> **Attention**: The container is based on named arguments.
Order does not matter but you need to name the arguments as they are defined in the constructor of a class itself.

Example:
```php
$config = [
    MongoDB\Client::class => [
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
]
```

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

Example:
```php
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
                    'stream' => 'my_file.log',
                    'level' => 100
                ]
            ]
        ]
    ],
];
```

#### Batching multiple calls

If a lot of calls need to be made with the same pattern, there is a possibility to use `batch` which allows
to define a method signature using `arguments` and list all calls in `batch`:

Example:
```php
RouteCollector::class => [
    'calls' => [[
        'method' => 'addRoute',
        'arguments' => [
            'httpMethod',
            'route',
            'handler',
        ],
        'batch' => [
            ['GET', '/api/v2', [Specifications::class, 'getApi']],
            ['GET', '/api/v2/users', [v2\Users::class, 'getAll']],
            ['GET', '/api/v2/users/{user}', [v2\Users::class, 'getOne']],
            ['POST', '/api/v2/users', [v2\Users::class, 'post']],
            ['PUT', '/api/v2/users/{user}', [v2\Users::class, 'put']],
            ['PATCH', '/api/v2/users/{users}', [v2\Users::class, 'patch']],
            ['DELETE', '/api/v2/users/{user}', [v2\Users::class, 'delete']],
        ]
    ]
]
```

### Reference to other services
If you want to pass another service you can wrap your value into `{service name}`. This will let the container to search for a service called 'service name'.

### Using Interfaces, abstract/parent classes or aliases
A service named with an interface name like `Psr\Log\LoggerInterface` can be configured to use specific implementation like `Monolog\Logger` via the 
keyword `use`.

Example:
```php
$config = [
    LoggerInterface::class => [
        'use' => Logger::class,
    ]
];
```
This will configure the dic to return an instance of Logger::class if an implementation of LoggerInteface::class is required.


### Values and environment variables
Passing values work like passing service references. The only difference is that static values must not be wrapped in `{}`.
It is also possible to read values from environment variables. This can be done like `{ENV(LOG_FOLDER)}` or with an optional default
value `{ENV(LOG_FOLDER,/tmp)}`. If the variable is found it will use the value of `LOG_FOLDER` otherwise the default value. If no default value is given and the env variable was not found an exception `Micro\Container\Exception\EnvVariableNotFound` will be thrown.

Example:
```php
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
                    'stream' => '{ENV(LOG_FOLDER),logs}/my_file.log',
                    'level' => 100
                ]
            ]
        ]
    ],
];
```

### Singletons
A service is by default a singleton. If a service once is created it will be used if another service requires the same dependency.
This behaviour might be changed to `singleton: false` which will always resolve the requested service.

Example:
```php
$config = [
    SmtpTransport::class => [
        'arguments' => [
            'server' => '127.0.0.1'
        ],
        'singleton' => false
    ]
];

$container = new Container($config);
$a = $container->get(SmtpTransport::class);
$b = $container->get(SmtpTransport::class);
```

`$a` and `$b` are different instances now. 

### Factories
Factories are usually static methods which return an instance of a class while only the factory knows how to construct such an object.

Example:
```php
$config = [
    SmtpTransport::class => [
        'use' => SmtpTransportFactory::class,
        'factory' => 'factory_method'
        'arguments' => [
            'server' => '127.0.0.1'
        ]
    ]
];

$container = new Container($config);
$transport = $container->get(SmtpTransport::class);
```
### Lazy services
Lazy services are great if you have very complex objects or just many of them. A service declared as `lazy` will be return as
a proxy object and as soon as it is really required it gets initialized. Proxy objects are implemented trough [Ocramius/ProxyManager](https://github.com/Ocramius/ProxyManager).

Let's say there is a PDO service and it is required by lots of other services and it does already connect to the database server within the constructor.
This is fine but may be not usable if you only rely on the connection at certain points in your app. 
By declaring it as a lazy service, a proxy object of PDO gets injected into your classes which require a PDO service and as soon as your
class access the PDO service it gets created as real object.

Example:
```php
$config = [
    PDO::class => [
        'arguments' => [
            'dsn' => 'mysql:127.0.0.1'
        ],
        'lazy' => true
    ]
];
```
Be careful with lazy services. Only use it if it makes sense.

### Lazy services wrapped in callbacks
Another way to achieve lazy services is to wrap them in a callback. The service only gets resolved if the callback gets executed.
While `lazy` will return an exact copy (proxy) instance of the service, `wrap` will return a closure `function(){return $service;}` whereas $service gets resolved as soon as the callback gets executed.

Example:
```php
$config = [
    PDO::class => [
        'arguments' => [
            'dsn' => 'mysql:127.0.0.1'
        ],
        'wrap' => true
    ]
];

$container = new Container($config);
//Note: PDO::class is only resolved to a callback now, there is no actual instance yet.
$pdo_callback = $container->get(PDO::class);

//create instance
$pdo = $pdo_callback();
```

### Exposing and nesting services
Services configured at the top level of the configuration are exposed by default. You can nest services via `services` to hide services within the container.
This leads to a cleaner and more readable configuration. Given the Monolog example the services `Monolog\Formatter\FormatterInterface` and `Psr\Log\LoggerInterface` are configured at the top level.
Therefore those can be requested directly from the container whereas `Monolog\Handler\StreamHandler` is a sub service of `Psr\Log\LoggerInterface` and can not be requested.
The container tries to look up services from the bottom to the top. If there is service configured with the name the container is looking for it takes that configuration and injects the service at this level.
If no service is found the container will look a level above and so on.
You can nest service as deep as you want.

Example:
```php
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
                    'stream' => '/my_file.log',
                    'level' => 100
                ]
            ]
        ]
    ],
];
```

In the above example the service `StreamHandler::class` is a sub service of `LoggerInterface::class` and can not be requested directly from the container.

### Configuring services via parent classes or interfaces
It is also possible to configure services of the same type with one declaration.
Of the same type means what parent classes or interfaces a certain class implements.
All service declarations get merged during requesting a service.

Example:
```php
$config = [
    JobInterface::class => [
        'arguments' => [
            'bar' => 'foo'
        ],
        'singleton' => true
    ],
    Job\C::class => [
        'arguments' => [
            'bar' => 'bar'
        ]
    ]
];

$container = new Container($config);
$a = $container->get(Job\A::class);
$b = $container->get(Job\B::class);
$c = $container->get(Job\C::class);
```

All implementations of `JobInterface::class` are now singletons and have a constructor argument `bar` set to foo
expect `Job\C::class` which is also a singleton but the constructor argument `bar` is set too bar.

This also works for nested services and the whole sub service tree get merged with declarations of the same type.
For example a sub service checks parent service declarations of the same and will merge those.

Let's say we have a job manager and a job class which implements the interface JobInterface:

Example:
```php
$config = [
    JobInterface::class => [
        'arguments' => [
            'bar' => 'foo'
        ],
    ],
    JobManager::class => [
        'arguments' => [
            'bar' => 'bar'
        ],
        'calls' => [
            [
                'method' => 'injectJob',
                'arguments' => ['job' => '{my_job}']
            ],
        ],
        'services' => [
            'my_job' => [
                'use' => Job::class
            ]
        ]
    ]
];

$container = new Container($config);
$container->get(JobManager::class);
```

The job `my_job` will get injected into the job manager with the constructor argument `bar => foo` since
we declared a common service configuration for `JobInterface::class`.
You may also declare JobInterface::class on the same nesting level as the job itself or as a child service.

If you do not want that a service looks for parent services of the same type and tries to merge the service configuration you 
can disable this feature by setting `merge` to `false` on the given service.


### Using method result as service
It is possible to define a service which does use the result of a method call of another service. Have a look at this example where we use the [MongoDB library](https://github.com/mongodb/mongo-php-library) and
from it an instance of `MongoDB\Database` but this instance must be created from `MongoDB\Client`.
```php
$config = [
    MongoDB\Client::class => [
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
        'calls' => [[
            'method' => 'selectDatabase',
            'arguments' => [
                'databaseName' => 'my_database'
            ],
            'select' => true
        ]]
    ]
]
```
Instead setting a specific class in the `use` statement you can wrap another service in `{}` to use that service as a parent service.
The service `MongoDB\Database` is now actually an instance of `MongoDB\Client`. 
We can declare a calls statement with the option `select` to `true`, this will tell the container, that we want to use the result of `selectDatabase`.

>**Note**: `calls` supports chaining of multiple method calls. You may also mix select=true/false methods but keep in mind that the order matters! All calls will get executed in the configured order.

This example will build an [elasticsearch client](https://github.com/elastic/elasticsearch-php) using first a factory and on the factory result a `setHosts` call gets executed and from that a `build` call gets executed whereas we use the result of as value for
the server `Elasticsearch\Elasticsearch\Client::class`.

```php
$config = [
    Elasticsearch\Elasticsearch\Client::class => [
        'use' => Elasticsearch\Elasticsearch\ClientBuilder::class,
        'factory' => 'create',
        'calls' => [
            [
                'method' => 'setHosts',
                'arguments' => ['hosts' => ["http://localhost:9200"]]
            ],
            [
                'method' => 'build',
                'select' => true,
            ]
        ],
    ],
]
```

>**Note**: The usage of the `selects` statement besides `calls` is deprecated as of v2.0.2 and gets removed in v3.0.0. The only supported option beginning with v3.0.0 is the `select` statement within `calls`.

### Make at runtime

Besides static configuration of the service tree, Micro\Container also allows to build instances at runtime. This is especially useful if 
service constructors need arguments which are only ever known at runtime.

>**Note**: `make()` is not compatible with PSR-11.

```php
$service = $container->make(JobInterface::class, [
    'arg1' => 'myvalue'
]);

Like everthing else, the parameters array excepts an array with named parameters to build the service. It is certainly possible 
to combine arguments at runtime and statically configured arguments within the dic configuration.

>**Note**: Dynamically created instances with `make()` are always `singleton: false`.
```
