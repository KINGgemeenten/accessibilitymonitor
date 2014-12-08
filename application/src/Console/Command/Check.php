<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\Check.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\ProcessInterface;
use Triquanta\AccessibilityMonitor\QuailInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a command to check a URL.
 */
class Check extends Command implements ContainerFactoryInterface {

  /**
   * The maximum time a process is allowed to run, in seconds.
   */
  const MAX_RUN_TIME = 40;

  /**
   * The number of items in the queue that should trigger an alert log item.
   *
   * @var int
   */
  protected $alertThreshold;

  /**
   * The number of items in the queue that should trigger an error log item.
   *
   * @var int
   */
  protected $errorThreshold;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The number of items in the queue that should trigger a notice log item.
   *
   * @var int
   */
  protected $noticeThreshold;

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
   * @param \Psr\Log\LoggerInterface
   * @param int $notice_threshold
   *   The number of items in the queue that should trigger a notice log item.
   * @param int $error_threshold
   *   The number of items in the queue that should trigger an error log item.
   * @param int $alert_threshold
   *   The number of items in the queue that should trigger an alert log item.
   */
  public function __construct(ProcessInterface $process_manager, StorageInterface $storage, QuailInterface $quail, LoggerInterface $logger, $notice_threshold, $error_threshold, $alert_threshold) {
    parent::__construct();
    $this->alertThreshold = $alert_threshold;
    $this->errorThreshold = $error_threshold;
    $this->logger = $logger;
    $this->noticeThreshold = $notice_threshold;
    $this->processManager = $process_manager;
    $this->quail = $quail;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('process'), $container->get('storage'), $container->get('quail'), $container->get('logger'), $container->getParameter('queue.threshold.notice'), $container->getParameter('queue.threshold.error'), $container->getParameter('queue.threshold.alert'));
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
    $count_before = $this->storage->countUrlsByStatus(Url::STATUS_SCHEDULED);

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

    // Check the queue health.
    $count_after = $this->storage->countUrlsByStatus(Url::STATUS_SCHEDULED);
    $level = $count_after > $this->alertThreshold ? LogLevel::ALERT : ($count_after > $this->errorThreshold ? LogLevel::ERROR : ($count_after > $this->noticeThreshold ? LogLevel::NOTICE : LogLevel::INFO));
    $this->logger->log($level, sprintf('%d URLs, of which %d newly added, are currently scheduled for testing.', $count_after, $count_after - $count_before));

    $output->writeln('<info>Done.</info>');
  }

}
