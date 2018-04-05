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
