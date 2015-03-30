<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\SolariumClient.
 */

namespace Triquanta\AccessibilityMonitor;

use Solarium\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a Solarium Solr client that lets dependencies be injected.
 */
class SolariumClient extends Client
{

    /**
     * Sets the event dispatcher.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
     *
     * @return $this
     */
    public function setEventDispatcher(
      EventDispatcherInterface $event_dispatcher
    ) {
        $this->eventDispatcher = $event_dispatcher;

        return $this;
    }

}
