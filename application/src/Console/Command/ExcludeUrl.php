<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\ExcludeUrl.
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
 * Provides a command to exclude a URL from testing.
 */
class ExcludeUrl extends Command implements ContainerFactoryInterface {

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
    $this->setName('url-exclude')
      ->addArgument('id', InputArgument::REQUIRED, "Either a numeric URL ID or the URL itself.");
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $id = $input->getArgument('id');
    if (is_numeric($id)) {
      $url = $this->storage->getUrlById($id);
      if (!$url) {
        throw new \InvalidArgumentException(sprintf('URL with ID %d does not exist.', $id));
      }
    }
    else {
      $url = $this->storage->getUrlByUrl($id);
      if (!$url) {
        throw new \InvalidArgumentException(sprintf('URL %s does not exist.', $id));
      }
    }
    $this->actions->excludeUrl($url);
  }

}
