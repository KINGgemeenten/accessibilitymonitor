<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\ProcessTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Triquanta\AccessibilityMonitor\Process;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Process
 * @requires extension pcntl
 * @requires extension posix
 */
class ProcessTest extends \PHPUnit_Framework_TestCase {

  /**
   * The process manager under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Process
   */
  protected $processManager;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->processManager = new Process();
  }

  /**
   * @covers ::getRegisteredProcessId
   * @covers ::register
   * @covers ::unregister
   */
  function testRegistration() {
    // Unregister this process so we start with a clean slate.
    $this->processManager->unregister();

    $method_get = new \ReflectionMethod($this->processManager, 'getRegisteredProcessId');
    $method_get->setAccessible(TRUE);

    // No process should be registered.
    $this->assertNull($method_get->invoke($this->processManager));

    // Register the current process and confirm this.
    $this->processManager->register();
    $this->assertSame(getmypid(), $method_get->invoke($this->processManager));

    // Unregister the current process and confirm this.
    $this->processManager->unregister();
    $this->assertNull($method_get->invoke($this->processManager));
  }

  /**
   * @covers ::isAnotherProcessRegistered
   * @covers ::register
   */
  function testConcurrentRegistration() {
    $this->processManager->register();
    // Registering the same process again should be ignored.
    $this->processManager->register();
    // Fork the process, so the new PID will differ from the registered one.
    pcntl_fork();
    try {
      $this->processManager->register();
      $this->fail();
    }
    catch (\RuntimeException $e) {
    }
  }

}
