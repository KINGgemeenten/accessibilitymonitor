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
  public function createWorker(Url $url, $queue_id) {
    return new PhantomQuailWorker($this->container->get('http_client'), $this->container->get('solr.client.phantom'), $this->container->get('phantomjs'), $url, $queue_id, $this->container->getParameter('google_pagespeed.api_url'), $this->container->getParameter('google_pagespeed.api_key'), $this->container->getParameter('google_pagespeed.api_strategy'));
  }

} 
