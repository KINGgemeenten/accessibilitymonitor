<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\ContainerFactoryInterface.
 */

namespace Triquanta\AccessibilityMonitor;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a console to the accessibility monitor application.
 */
interface ContainerFactoryInterface {

  /**
   * Constructs a new instance using the dependency injection container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container);

}
