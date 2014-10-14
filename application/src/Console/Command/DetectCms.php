<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\DetectCms.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\PhantomJsInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a command to detect a CMS.
 */
class DetectCms extends Command implements ContainerFactoryInterface {

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * The Phantom JS manager.
   *
   * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface
   */
  protected $phantomJs;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Triquanta\AccessibilityMonitor\PhantomJsInterface $phantom_js
   */
  public function __construct(StorageInterface $storage, PhantomJsInterface $phantom_js) {
    parent::__construct();
    $this->storage = $storage;
    $this->phantomJs = $phantom_js;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('storage'), $container->get('phantomjs'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('detect-cms');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach ($this->storage->getUrlsByNotStatus(Url::STATUS_EXCLUDED) as $url) {
      $detected_apps = implode('|', $this->phantomJs->getDetectedApps($url->getUrl()));
      $url->setCms($detected_apps);
      $this->storage->saveUrl($url);
      $output->writeln(sprintf('<info>%s is powered by: %s</info>', $url->getUrl(), $detected_apps));
    }
  }

}
