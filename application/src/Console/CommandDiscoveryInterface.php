<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\CommandDiscoveryInterface.
 */

namespace Triquanta\AccessibilityMonitor\Console;

/**
 * Defines a way to discover available console commands.
 */
interface CommandDiscoveryInterface {

  /**
   * Gets all command class names.
   *
   * @return string[]
   *   The fully qualified class names of the available commands.
   */
  public function getCommands();

}
