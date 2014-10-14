<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\ProcessInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a process manager.
 */
interface ProcessInterface {

  /**
   * Checks whether another instance of this PHP script is already running.
   *
   * @return bool
   */
  public function isAnotherProcessRegistered();

  /**
   * Registers this instance of the script.
   *
   * @throws \RuntimeException
   *   Thrown when this process cannot be registered, because another process is
   *   still running.
   */
  public function register();

  /**
   * Unregisters this instance of the script.
   */
  public function unregister();

  /**
   * Kills other instances of the script.
   */
  public function killOtherProcess();

}
