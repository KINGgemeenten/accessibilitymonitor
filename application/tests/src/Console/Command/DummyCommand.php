<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\DummyCommand.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Provides a dummy command.
 */
class DummyCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName(__CLASS__);
  }

}
