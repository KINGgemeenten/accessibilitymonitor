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
   * A URL that is scheduled for testing.
   */
  const STATUS_SCHEDULED = 0;

  /**
   * A URL that is currently being tested.
   */
  const STATUS_TESTING = 1;

  /**
   * A URL for which testing has successfully completed.
   */
  const STATUS_TESTED = 2;

  /**
   * A URL for which testing was aborted due to errors.
   */
  const STATUS_ERROR = 3;

}
