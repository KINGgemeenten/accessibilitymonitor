Accessibility Monitor
=====================

[![Build Status](https://travis-ci.org/KINGgemeenten/accessibilitymonitor.svg?branch=feature/rabbitmq)](https://travis-ci.org/KINGgemeenten/accessibilitymonitor)

Requirements
============
* PHP 5.4+
* [Composer](http://getcomposer.org)
* An operating system with Upstart (Ubuntu is used for all examples)

Installation
============

Application
-----------
* `cd ./application`
* `composer install`
* `cp ./application/container_overrides_example.yml ./application/container_overrides.yml` and edit the values. 

Worker manager
--------------

* `mkdir /etc/accessibilitymonitor`
* `echo $MAX > /etc/accessibilitymonitor/max_worker_count`, where `$MAX` is the maximum number of concurrent workers for
 the machine.
* `` echo `pwd`/application/bin/tam start-worker > /etc/accessibilitymonitor/worker ``
* `cp ./application/scripts/accessibilitymonitor.conf /etc/init/`
* `start accessibilitymonitor`

Development
===========

PSR-1 & PSR-2
-------------
All code must be written according the
[PSR-1](http://www.php-fig.org/psr/psr-1/) and
[PSR-2](http://www.php-fig.org/psr/psr-2/) guidelines.

PSR-4
-----
Class and interface autoloading is done using
[PSR-4](http://www.php-fig.org/psr/psr-4/) using the following namespace
mappings:

* `\Triquanta\AccessibilityMonitor` maps to `./applicaiton/src`
* `\Triquanta\Tests\AccessibilityMonitor` maps to `./application/tests/src`

Testing
-------
The library comes with [PHPUnit](https://phpunit.de/)-based tests that can be
run using `./application/phpunit.xml.dist`. All tests are located in
`\Triquanta\Tests\AccessibilityMonitor`.

Virtual machine
---------------
See ./vm/README.md.
