<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\ContainerFactoryDummyCommand.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;

/**
 * Provides a dummy command with a container factory method.
 */
class ContainerFactoryDummyCommand extends Command implements ContainerFactoryInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('service_container'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName(__CLASS__);
  }

}
