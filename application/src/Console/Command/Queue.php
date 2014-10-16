<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\Queue.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ActionsInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\Website;

/**
 * Provides a command to queue scheduled websites and URLs.
 */
class Queue extends Command implements ContainerFactoryInterface {

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface
   */
  protected $actions;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\ActionsInterface $actions
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Psr\Log\LoggerInterface
   */
  public function __construct(ActionsInterface $actions, StorageInterface $storage, LoggerInterface $logger) {
    parent::__construct();
    $this->actions = $actions;
    $this->logger = $logger;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('actions'), $container->get('storage'), $container->get('logger'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('queue');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach ($this->storage->getPendingActions() as $action) {
      if ($action->getAction() == $action::ACTION_ADD_URL) {
        // If no website exists for this URL, skip it.
        $website_id = $this->storage->getWebsiteIdForNestedUrl($action->getUrl());
        if (!$website_id) {
          $this->logger->info(sprintf('Skipped adding URL %s, because the website for it was not added yet.', $action->getUrl()));
          continue;
        }
        else {
          $url = new Url();
          $url->setUrl($action->getUrl())
            ->setWebsiteId($website_id);
          $this->actions->addUrl($url);
        }
      }
      elseif ($action->getAction() == $action::ACTION_EXCLUDE_URL) {
        $url = $this->storage->getUrlByUrl($action->getUrl());
        $this->actions->excludeUrl($url);
      }
      elseif ($action->getAction() == $action::ACTION_ADD_WEBSITE) {
        $website = new Website();
        $website->setUrl($action->getUrl());
        $this->actions->addWebsite($website);
      }
      elseif ($action->getAction() == $action::ACTION_EXCLUDE_WEBSITE) {
        $website = $this->storage->getWebsiteByUrl($action->getUrl());
        $this->actions->excludeWebsite($website);
      }
      elseif ($action->getAction() == $action::ACTION_RESCAN_WEBSITE) {
        $website = $this->storage->getWebsiteByUrl($action->getUrl());
        $this->actions->rescanWebsite($website);
      }
      $action->setTimestamp(time());
      $this->storage->saveAction($action);
    }
  }

}
