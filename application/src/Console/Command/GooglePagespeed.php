<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\GooglePagespeed.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\GooglePagespeedInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;

/**
 * Provides a command to test a URL using Google PageSpeed.
 */
class GooglePagespeed extends Command implements ContainerFactoryInterface {

  /**
   * The API fetch limit.
   *
   * @var string
   */
  protected $apiFetchLimit;

  /**
   * The Google Pagespeed tester.
   *
   * @var \Triquanta\AccessibilityMonitor\GooglePagespeedInterface
   */
  protected $googlePagespeed;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Triquanta\AccessibilityMonitor\GooglePagespeedInterface $google_pagespeed
   * @param int $api_fetch_limit
   */
  public function __construct(StorageInterface $storage, GooglePagespeedInterface $google_pagespeed, $api_fetch_limit) {
    parent::__construct();
    $this->apiFetchLimit = $api_fetch_limit;
    $this->googlePagespeed = $google_pagespeed;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('storage'), $container->get('google_pagespeed'), $container->getParameter('google_pagespeed.api_fetch_limit'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('google-pagespeed');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // @todo Convert the logic to a re-usable service.
    $urls = $this->storage->getUrlsWithoutGooglePagespeedScore($this->apiFetchLimit);
    $output->writeln(sprintf('<info>Testing %d URLs.</info>', count($urls)));
    foreach($urls as $url) {
      $output->writeln(sprintf('<info>Testing %s.</info>', $url->getUrl()));

      $result = $this->googlePagespeed->test($url->getUrl());

      // Save score if we have one.
      if(isset($result->responseCode) && $result->responseCode == 200) {
        $output->writeln(sprintf('<info>%s returned an HTTP %d response.</info>', $url->getUrl(), $result->responseCode));
        $url->setGooglePagespeedResult($result->score);
        $this->storage->saveUrl($url);
      }
      else {
        $output->writeln(sprintf('<error>%s returned an HTTP %d response.</error>', $url->getUrl(), $result->responseCode));
      }
    }
    $output->writeln('<info>Done.</info>');
  }

}
