<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\ExcludeWebsite.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ActionsInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;

/**
 * Provides a command to exclude a website from testing.
 */
class ExcludeWebsite extends Command implements ContainerFactoryInterface {

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface
   */
  protected $actions;

  /**
   * The storage.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\ActionsInterface $actions
   */
  public function __construct(ActionsInterface $actions, StorageInterface $storage) {
    parent::__construct();
    $this->actions = $actions;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('actions'), $container->get('storage'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('website-exclude')
      ->addArgument('id', InputArgument::REQUIRED, "Either a numeric website ID or the website's root URL.");
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $id = $input->getArgument('id');
    if (is_numeric($id)) {
      $website = $this->storage->getWebsiteById($id);
      if (!$website) {
        throw new \InvalidArgumentException(sprintf('Website with ID %d does not exist.', $id));
      }
    }
    else {
      $website = $this->storage->getWebsiteByUrl($id);
      if (!$website) {
        throw new \InvalidArgumentException(sprintf('Website with URL %s does not exist.', $id));
      }
    }
    $this->actions->excludeWebsite($website);
  }

}
