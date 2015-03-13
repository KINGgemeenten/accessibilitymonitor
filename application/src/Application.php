<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Application.
 */

namespace Triquanta\AccessibilityMonitor;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Provides application-level functionality.
 */
class Application {

  /**
   * The application's version.
   */
  const VERSION = '3';

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  static protected $container;

  /**
   * Bootstraps the application.
   */
  public static function bootstrap() {
    $container = new ContainerBuilder();
    $file_locator = new FileLocator(array(__DIR__ . '/..'));
    $service_loader = new YamlFileLoader($container, $file_locator);
    $service_loader->load('container.yml');
    $override_file = 'container_overrides.yml';
    if (file_exists(__DIR__ . '/../' . $override_file)) {
      $service_loader->load($override_file);
    }
    $container->setParameter('root_directory', dirname(__DIR__));
    $container->compile();
    static::setContainer($container);
  }

  /**
   * Gets the dependency injection container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public static function getContainer() {
    return static::$container;
  }

  /**
   * Sets the dependency injection container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public static function setContainer(ContainerInterface $container) {
    static::$container = $container;
  }

}
