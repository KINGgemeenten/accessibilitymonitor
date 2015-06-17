# Gewoon Toegankelijk Virtual Machine
This directory contains a Vagrant VM that provides and supports all 
[Gewoon Toegankelijk](http://gewoontoegankelijk.nl) functionality except for 
the website. This is useful for local development, where the website can run on 
any platform and all other services, except the Nutch solr core, are run within 
this VM. To setp up a local environment, all installation instructions, except 
those for the website, must be executed within this VM.

All relative paths are relative to the directory of this file.

## Requirements
- [Ansible](http://ansible.com)
- [Vagrant](https://vagrantup.com)

## Installation
- `ansible-playbook init.yml -i ansible_hosts`
- `vagrant up`
- Execute `../sql-dump/inspector-N.sql` in the `inspector` MySQL database 
  within the VM, where `N` is the highest available version number.
- `vagrant ssh`
- `sudo su`
- `cd /opt/accessibilitymonitor`
- Perform the testbot installation tasks as documented in
  [../README.md](../README.md).

# VM details
The VM will be accessible at `192.168.50.5`. 
