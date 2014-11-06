<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\QuailWorkerFactory.
 */

namespace Triquanta\AccessibilityMonitor;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Quail worker factory.
 */
class QuailWorkerFactory implements QuailWorkerFactoryInterface {

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public function createWorker(Url $url, Website $website, $queue_id, $determine_cms, $execute_google_pagespeed) {
    return new PhantomQuailWorker($this->container->get('google_pagespeed'), $this->container->get('solr.client.phantom'), $this->container->get('phantomjs'), $url, $website, $queue_id, $determine_cms, $execute_google_pagespeed);
  }

} 
