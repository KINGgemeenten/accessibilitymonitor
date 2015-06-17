# Accessibility Monitor

[![Build Status](https://travis-ci.org/KINGgemeenten/accessibilitymonitor.svg?branch=feature/rabbitmq)](https://travis-ci.org/KINGgemeenten/accessibilitymonitor)

This project contains the testing application for 
[Gewoon Toegankelijk](http://gewoontoegankelijk.nl), the daemon to start 
workers through the application, and a VM for testing.

All relative paths are relative to the repository root.

## Requirements
* [PHP](http://php.net) 5.5+
* [Composer](http://getcomposer.org)
* An operating system with [Upstart](http://upstart.ubuntu.com/) (Ubuntu is 
  used for all examples)

## Usage
This project is a [Symfony](http://symfony.com/) application that is built 
around Symfony's
[dependency injection component](http://symfony.com/doc/current/components/dependency_injection/introduction.html).
`\Triquanta\AccessibilityMonitor\Application` is responsible for booting the 
application and building the service container.

The service container is configured in
[./application/container.yml](./application/container.yml) and contains service 
definitions and simple configuration parameters. Existing configuration can be 
extended and overridden by creating `./application/container_overrides.yml`. 
Both files contain 
[YAML container configuration](http://symfony.com/doc/current/components/dependency_injection/introduction.html#setting-up-the-container-with-configuration-files). 

[./application/bin/tam](./application/bin/tam) is the application's CLI, which 
can control all of its functionality. It is built on Symfony's 
[console component](http://symfony.com/doc/current/components/console/introduction.html).

## Installation

### Testbot

* Testing application
    * `cd ./application`
    * `composer install`
    * `cp ./application/container_overrides_example.yml ./application/container_overrides.yml` 
      and edit/override the configuration.
    * Make sure that the value of the `tmp_directory` configration is a 
      directory path on the system that is writable by the user under which the 
      workers run. The path defaults to `/tmp/accessibilitymonitor` and can be 
      overridden in `container_overrides.yml`.
* Worker manager (Upstart daemon)
    * `mkdir /etc/accessibilitymonitor`
    * `echo $MAX > /etc/accessibilitymonitor/max_worker_count`, where `$MAX` is 
      the maximum number of concurrent workers for the machine.
    * `` echo `pwd`/application/bin/tam start-worker > /etc/accessibilitymonitor/worker ``
    * `` echo `pwd`/application/bin/tam retest > /etc/accessibilitymonitor/retest ``
    * `cp ./application/scripts/accessibilitymonitor.conf /etc/init/`
    * `start accessibilitymonitor`

### Test result metadata & results storage

* Ensure a database has been created and its credentials are configured in the
  testing application's `./application/container_overrides.yml`.
* Run `./sql-dump/inspector-N.sql` (where `N` is the highest available version
  number) in the database ensured in the previous step.

## Logging
System events are logged to the console output, to file, and severe events also 
result in emails being sent to the project maintainers. This behavior is 
configured in the service container.

## Development

### PSR-2
All code must be written according the [PSR-2](http://www.php-fig.org/psr/psr-2/) guidelines.

### PSR-3
Logging is done through [Monolog](https://github.com/Seldaek/monolog). Its logger is used according 
[PSR-3](http://www.php-fig.org/psr/psr-3/).

### PSR-4
Class and interface autoloading is done using
[PSR-4](http://www.php-fig.org/psr/psr-4/) using the following namespace
mappings:

* `\Triquanta\AccessibilityMonitor` maps to [./application/src](./application/src)
* `\Triquanta\Tests\AccessibilityMonitor` maps to [./application/tests/src](./application/tests/src)

### Testing
The library comes with [PHPUnit](https://phpunit.de/)-based tests that can be
run using [./application/phpunit.xml.dist](./application/phpunit.xml.dist). 
All tests are located in `\Triquanta\Tests\AccessibilityMonitor`.

The Github repository is hooked up to [Travis CI](https://travis-ci.org/KINGgemeenten/accessibilitymonitor), which runs 
the tests after pushes, or for pull requests.

### Virtual machine
See [./vm/README.md](./vm/README.md).
