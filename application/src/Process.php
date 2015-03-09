<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Process.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Provides a process manager.
 */
class Process implements ProcessInterface {

  /**
   * The name of the file in which to store the process ID.
   *
   * @var string
   */
  protected $fileName;

  /**
   * Constructs a new instance.
   */
  public function __construct() {
    $extensions = array('pcntl', 'posix');
    foreach ($extensions as $extension) {
      if (!extension_loaded($extension)) {
        throw new \RuntimeException(sprintf('\%s requires the %s PHP extension to be loaded.', __CLASS__, $extension));
      }
    }

    $this->fileName = __DIR__ . '/accessibility_monitor.pid';
  }

  /**
   * Gets the registered process ID.
   *
   * @return int|null
   *   The process ID or NULL if no process is currently registered.
   */
  protected function getRegisteredProcessId() {
    if (file_exists($this->fileName)) {
      $pid = trim(file_get_contents($this->fileName));
      if (is_numeric($pid)) {
        return (int) $pid;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnotherProcessRegistered() {
    $pid = $this->getRegisteredProcessId();
    return is_int($pid) && $pid !== getmypid() && posix_kill($pid, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function register() {
    if ($this->isAnotherProcessRegistered()) {
      throw new \RuntimeException('Cannot register this script, because another instance is still registered.');
    }

    file_put_contents($this->fileName, getmypid());
  }

  /**
   * {@inheritdoc}
   */
  public function unregister() {
    // Prevent deletion of the PID file if it belongs to another instance.
    if (!$this->isAnotherProcessRegistered()) {
      // Deliberately suppress any errors that may occur when the file does not
      //  exist, because two processes tried to delete it at the same time.
      @unlink($this->fileName);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function killOtherProcess() {
    if ($this->isAnotherProcessRegistered()) {
      shell_exec(escapeshellcmd('kill -KILL ' . $this->getRegisteredProcessId()));
    }
  }

}
