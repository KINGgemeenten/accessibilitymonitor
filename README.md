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
