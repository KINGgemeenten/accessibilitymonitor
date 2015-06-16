# Gewoon Toegankelijk Virtual Machine
This directory contains a Vagrant VM that provides and supports all 
[Gewoon Toegankelijk](http://gewoontoegankelijk.nl) functionality except for 
the website.
All relative paths are relative to the directory of this file.

## Requirements
- [Ansible](http://ansible.com)
- [Vagrant](https://vagrantup.com)

## Installation
- `ansible-playbook init.yml -i ansible_hosts`
- `vagrant up`

# VM details
The VM will be accessible at `192.168.50.5`.
