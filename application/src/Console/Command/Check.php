<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\Check.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\ProcessInterface;
use Triquanta\AccessibilityMonitor\QuailInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;

/**
 * Provides a command to check a URL.
 */
class Check extends Command implements ContainerFactoryInterface {

  /**
   * The maximum time a process is allowed to run, in seconds.
   */
  const MAX_RUN_TIME = 40;

  /**
   * The process manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ProcessInterface
   */
  protected $processManager;

  /**
   * The Quail manager.
   *
   * @var \Triquanta\AccessibilityMonitor\QuailInterface
   */
  protected $quail;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\ProcessInterface $process_manager
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Triquanta\AccessibilityMonitor\QuailInterface $quail
   */
  public function __construct(ProcessInterface $process_manager, StorageInterface $storage, QuailInterface $quail) {
    parent::__construct();
    $this->processManager = $process_manager;
    $this->quail = $quail;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('process'), $container->get('storage'), $container->get('quail'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('check');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($this->processManager->isAnotherProcessRegistered()) {
      $output->writeln('<info>Another process is already running.</info>');
      $last_analysis_timestamp = $this->storage->getUrlLastAnalysisDateTime();
      if ((time() - $last_analysis_timestamp) > self::MAX_RUN_TIME) {
        $this->processManager->killOtherProcess();
        $output->writeln(sprintf('<info>Killed the other process because it has not done anything for more than %d seconds.</info>', self::MAX_RUN_TIME));
      }
      else {
        return;
      }
    }

    $this->quail->test();
    $output->writeln('<info>Done.</info>');
  }

}
