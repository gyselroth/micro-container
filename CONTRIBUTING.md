# Contribute to micro-container
Did you find a bug or would you like to contribute a feature? You are certainly welcome to do so.
Please always fill an [issue](https://github.com/gyselroth/micro-container/issues/new) first to discuss the matter.
Do not start development without an open issue otherwise we do not know what you are working on. 

## Bug
If you just want to fill a bug report, please open your [issue](https://github.com/gyselroth/micro-container/issues/new).
We are encouraged to fix your bug to provide best software in the opensource community.

## Security flaw
Do not open an issue for a possible security vulnerability, to protect yourself and others please contact <opensource@gyselroth.net>
to report your concern.

### Get the base
```
git clone https://github.com/gyselroth/micro-container.git
```

### Install dependencies
To setup your development base you can make use of the the make buildtool to install all dependencies:
```
make deps 
```

### Make
Always execute make via `docker exec` if your are developing with the balloon docker image.


## Testsuite
Execute the testsuite before push:
```
make test
```

## Git
You can clone the repository from:
```
git clone https://github.com/gyselroth/micro-container.git
```

## Building
Besides npm scripts like build and start you can use make to build this software. The following make targets are supported:
* `build` Build software, but do not package
* `clean` Clear build and dependencies
* `deps` Install dependencies
* `test` Execute testsuite
* `phpcs-fix` Execute phpcs
* `phpstan` Execute phpstan

## Git commit 
Please make sure that you always specify the number of your issue starting with a hastag (#) within any git commits.

## Pull Request
You are absolutely welcome to submit a pull request which references an open issue. Please make sure you're follwing coding standards 
and be sure all your modifications pass the build.
[![Build Status](https://travis-ci.org/gyselroth/micro-container.svg)](https://travis-ci.org/gyselroth/micro-container)

## Code of Conduct
Please note that this project is released with a [Contributor Code of Conduct](https://github.com/gyselroth/micro-container/CODE_OF_CONDUCT.md). By participating in this project you agree to abide by its terms.

## License
This software is freely available under the terms of [MIT](https://github.com/gyselroth/micro-container/LICENSE), please respect this license
and do not contribute software parts which are not compatible to the MIT license.

## Editor config
This repository gets shipped with an .editorconfig configuration. For more information on how to configure your editor please visit [editorconfig](https://github.com/editorconfig).

## Code policy
Add the following script to your git pre-commit hook file, otherwise your build will fail if you do not following code style:

```
./vendor/bin/php-cs-fixer fix --config=.php_cs.dist -v
```

This automatically converts your code into the code style guidelines of this project otherwise your build will fail!
