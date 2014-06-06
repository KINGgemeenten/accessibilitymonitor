# Vagrant development.

This repository can be used with vagrant.
In order to use it, the following steps should be made:

## Step 1: Import required server configuration repository

 Go to the project root and run:

    ansible-playbook init.yml


## Step 2: Vagrant up

Take the vagrant machine up:

    vagrant up

This will take the vagrant machine up and will provision it. If this stucks, then run

    vagrant provision

## Step 3: Debugging using xdebug and phpStorm

In order to be able to use xdebug, you should perform the following steps:

1. Add a php server to phpStorm, for instance inspector.dev
2. Enable path mappings in the server and map siteinspector to /opt/siteinspector
3. run the script (inspect.php) the following way:

    PHP_IDE_CONFIG="serverName=siteinspector.dev" php inspect.php