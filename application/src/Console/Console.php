<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Console.
 */

namespace Triquanta\AccessibilityMonitor\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Constructs a new instance.
   */
  public function __construct(ContainerInterface $container, CommandDiscoveryInterface $command_discovery, LoggerInterface $logger, EventDispatcherInterface $event_dispatcher, PhantomJsInterface $phantom_js) {
    parent::__construct('Triquanta Accessibility Monitor', Application::VERSION);
    $this->setDispatcher($event_dispatcher);

    $this->container = $container;
    $this->logger = $logger;

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

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    try {
      parent::doRun($input, $output);
    }
    catch (\Exception $e) {
      $this->logger->emergency(sprintf('%s in %s on line %d.', $e->getMessage(), $e->getFile(), $e->getLine()));
      throw $e;
    }
  }

}
