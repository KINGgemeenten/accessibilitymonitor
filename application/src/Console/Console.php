<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Console.
 */

namespace Triquanta\AccessibilityMonitor\Console;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Triquanta\AccessibilityMonitor\Application;
use Triquanta\AccessibilityMonitor\PhantomJsInterface;

/**
 * Provides a console to the accessibility monitor application.
 */
class Console extends ConsoleApplication {

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * Constructs a new instance.
   */
  public function __construct(ContainerInterface $container, CommandDiscoveryInterface $command_discovery, EventDispatcherInterface $event_dispatcher, PhantomJsInterface $phantom_js) {
    $phantom_js->killStalledProcesses();
    parent::__construct('Triquanta Accessibility Monitor', Application::VERSION);
    $this->setDispatcher($event_dispatcher);

    $this->container = $container;

    foreach ($command_discovery->getCommands() as $class_name) {
      if (in_array('Triquanta\AccessibilityMonitor\ContainerFactoryInterface', class_implements($class_name))) {
        /** @var \Triquanta\AccessibilityMonitor\ContainerFactoryInterface $class_name */
        $command = $class_name::create($this->container);
      }
      else {
        $command = new $class_name();
      }
      $this->add($command);
    }
  }

}
