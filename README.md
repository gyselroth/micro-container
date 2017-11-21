# Micro (Yet another PHP library)
...but no shit

[![Build Status](https://travis-ci.org/gyselroth/micro-container.svg?branch=master)](https://travis-ci.org/gyselroth/micro-container)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gyselroth/micro-container/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gyselroth/micro-container/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/gyselroth/micro-container.svg)](https://packagist.org/packages/gyselroth/micro-container)
[![GitHub release](https://img.shields.io/github/release/gyselroth/micro-container.svg)](https://github.com/gyselroth/micro-container/releases)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/gyselroth/micro-container/master/LICENSE)

## Description
Micro provides minimalistic core features to write a new Application. Instead providing a rich featured fatty library it only provides a couple of namespaces. It comes with a logger (and multiple adapters), configuration parser, HTTP routing/response, Authentication (and multiple adapters) and some wrapper around databases and ldap.

* \Micro\Auth
* \Micro\Config
* \Micro\Container
* \Micro\Http
* \Micro\Log

## Requirements
The library is only >= PHP7.1 compatible.

## Download
The package is available at packagist: https://packagist.org/packages/gyselroth/micro-container

To install the package via composer execute:
```
composer require gyselroth/micro-container
```
