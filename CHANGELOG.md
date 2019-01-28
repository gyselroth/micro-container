## 2.1.0
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon Jan 28 11:26:33  CET 2019

* [FEATURE] Build instances with runtime arguments #12 
* [CHANGE] singleton: true should be the default (which it is), but true/false should be switched. #13 
* [CHANGE] Removed the deprecated selects functionality (use select: true for calls statements)


## 2.0.3
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Dez 19 11:15:31 CET 2018

* [FIX] "expected to be a reference, value given in" for arguments as refernece with default value #9


## 2.0.2
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon Nov 19 14:40:31 CET 2018

* [CHANGE] Chaining calls and selects calls in a mixed declaration. #7


## 2.0.1
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Sept 12 14:02:23 CEST 2018

* [FIX] fixed service Micro\Container\RuntimeContainer instead Psr\Container\ContainerInterface


## 2.0.0
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Jun 06 10:19:23 CEST 2018

* [CHANGE] ::has() now return true even if the service is not yet resolve but could
* [FEATURE] Added support for factory calls (static method call), see readme
* [FEATURE] Added support for lazy callbacks, see readme
* [CHANGE] Removed support for addService(),getParent(),setParent() to only support the psr11 supported public functions
* [CHANGE] Added new unit tests
* [CHANGE] If a service class depends on itself and gets not manually resolved a RuntimeException gets thrown instead Exception\InvalidConfiguration


## 1.0.1
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu Apr 05 20:44:05 CEST 2018

* [FEATURE] It is now possible to disable service options with null
* [FIX] Fixed undefined index lazy if merge is set to false, (correctly merge service definition with defaults)
* [FIX] If service class is not resolvable or an interface an exception of type Exception\InvalidConfiguration gets thrown instead a php internal error that a interface can not be used as a class
* [FIX] Sets argument to null if it is typehinted and has a default value of null (instead the php 7.1 ?Typehint declaration) 
* [CHANGE] Split Container into Container and AbstractContainer
* [CHANGE] Added new unit tests


## 1.0.0
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Mar 14 10:39:05 CET 2018

* [FEATURE] Configure services of the same type via parent classes or interfaces
* [FEATURE] Support for singletons
* [FEATURE] Support for lazy services
* [FEATURE] Disabling service configuration merge by setting merge to false
* [CHANGE] Added various new unit tests
* [CHANGE] Exracted configuration into Micro\Container\Config
* [FIX] Fixed multiple env variables declared in the same argument string
* [FIX] Fixed parent linking services of the same type


## 0.1.4
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri Feb 23 13:34:01 CET 2018

* [FIX] It is not possible to declare multiple ENV variables within one string
* [CHANGE] Removed deprecated getNew(), use get()
