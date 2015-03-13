Accessibility Monitor
=====================

[![Build Status](https://travis-ci.org/KINGgemeenten/accessibilitymonitor.svg?branch=release%2F20141023-01-v1.0)](https://travis-ci.org/KINGgemeenten/accessibilitymonitor)

To use the virtual machine, read ./vm/README.md.

POST INSTALLATION INSTRUCTIONS

- Installeer de crontab (gebruiker root)

PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin
* * * * * /opt/accessibilitymonitor/application/bin/tam check >> /var/log/inspect.log 2>&1


- Draai composer om de vendor map te maken

/opt/accessibilitymonitor/application$ composer install


- Vraag een sneltoets aan op een website om de database op de inspector server te vullen

Dit zorgt er voor dat de Solr omgeving (phantomcore) gevuld wordt. En dit zorgt er weer voor dat je lokale omgeving
weet welke velden in Solr staan.

- Let op dat je in de servercontrol niet develop maar master uitcheckt!!!! (in eerste instantie)

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
