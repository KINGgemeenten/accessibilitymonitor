<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\TestingStatusInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines testing statuses.
 */
interface TestingStatusInterface {

  /**
   * @todo Add a description.
   */
  const STATUS_SCHEDULED = 0;

  /**
   * @todo Add a description.
   */
  const STATUS_TESTING = 1;

  /**
   * @todo Add a description.
   */
  const STATUS_TESTED = 2;

  /**
   * @todo Add a description.
   */
  const STATUS_ERROR = 3;

  /**
   * @todo Add a description.
   */
  const STATUS_EXCLUDED = 4;

}
