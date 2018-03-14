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
