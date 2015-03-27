<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\YamlCommandDiscovery.
 */

namespace Triquanta\AccessibilityMonitor\Console;

use Symfony\Component\Yaml\Yaml;

/**
 * Discovers console commands through a YAML file.
 */
class YamlCommandDiscovery implements CommandDiscoveryInterface
{

    public function getCommands()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/commands.yml'));
    }

}
