<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\PhantomJs.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Provides a Phantom JS manager.
 */
class PhantomJs implements PhantomJsInterface {

  /**
   * The path to the Phantom JS executable.
   *
   * @var string
   */
  protected $executable;

  /**
   * The application's root directory.
   *
   * @var string
   */
  protected $rootDirectory;

  /**
   * The Phantom JS timeout.
   *
   * @var int
   *   A period in seconds.
   */
  protected $timeout;

  /**
   * Constructs a new instance.
   *
   * @param string $executable
   * @param int $timeout
   * @param string $root_directory
   */
  public function __construct($executable, $timeout, $root_directory) {
    $this->executable = $executable;
    $this->rootDirectory = $root_directory;
    $this->timeout = $timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function getDetectedApps($url) {
    // Add http, if not present.
    if (! preg_match('/^http/', $url)) {
      $url = 'http://' . $url;
    }
    $command = $this->executable . ' --ignore-ssl-errors=yes ' . $this->rootDirectory . '/node_modules/phantalyzer/phantalyzer.js ' . $url;
    $output = shell_exec($command);
    $preg_split = preg_split("/((\r?\n)|(\r\n?))/", $output);
    $detectedAppsArray = array();
    foreach ($preg_split as $line) {
      // Check the line detectedApps.
      if ($line != '' && preg_match("/^detectedApps/", $line)) {
        $detectedApps = str_replace('detectedApps: ', '', $line);
        $detectedAppsArray = explode('|', $detectedApps);
      }
      // Also check the generator.
      if ($line != '' && preg_match('/<meta name="generator" content="([A-Za-z\s]*)"/', $line, $matches)) {
        $generator = $matches[1];
      }
    }
    // If a generator is found, add it to the detected apps.
    if (isset($generator)) {
      // Add the generator to the detected apps.
      $detectedAppsArray[] = $generator;
    }

    return $detectedAppsArray;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuailResults($url) {
    $command = $this->executable . ' --ignore-ssl-errors=yes ' . $this->rootDirectory . '/phantomquail.js ' . $url;
    // Print some debug info.
//    $this->logger->debug('Starting phantomjs');
    $output = $this->execTimeout($command, $this->timeout);
//    $this->logger->debug('Phantomjs executed succesfully.');

    return $output;
  }

  /**
   * Execute a command and return it's output. Either wait until the command exits or the timeout has expired.
   *
   * This function is based on the following solution:
   * http://blog.dubbelboer.com/2012/08/24/execute-with-timeout.html
   *
   * @param string $cmd     Command to execute.
   * @param number $timeout Timeout in seconds.
   * @return string Output of the command.
   * @throws \Exception
   */
  protected function execTimeout($cmd, $timeout) {
    $timedOut = true;
    // File descriptors passed to the process.
    $descriptors = array(
      0 => array('pipe', 'r'),  // stdin
      1 => array('pipe', 'w'),  // stdout
      2 => array('pipe', 'w')   // stderr
    );

//    $this->logger->debug('Openining processes');
    // Start the process.
    $process = proc_open('exec ' . $cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
      throw new \Exception('Could not execute process');
    }

    // Set the stdout stream to none-blocking.
    stream_set_blocking($pipes[1], 0);

    // Turn the timeout into microseconds.
    $timeout = $timeout * 1000000;

    // Output buffer.
    $buffer = '';

    // While we have time to wait.
    while ($timeout > 0) {
      $start = microtime(true);

      // Wait until we have output or the timer expired.
      $read  = array($pipes[1]);
      $other = array();
//      $this->logger->debug('Before stream_select');
      stream_select($read, $other, $other, 0, $timeout);

      // Get the status of the process.
      // Do this before we read from the stream,
      // this way we can't lose the last bit of output if the process dies between these functions.
      $status = proc_get_status($process);

//      $this->logger->debug('Getting stream content.');
      // Read the contents from the buffer.
      // This function will always return immediately as the stream is none-blocking.
      $buffer .= stream_get_contents($pipes[1]);
//      $this->logger->debug('Buffer filled');

      if (!$status['running']) {
        $timedOut = false;
        // Break from this loop if the process exited before the timeout.
        break;
      }

      // Subtract the number of microseconds that we waited.
      $timeout -= (microtime(true) - $start) * 1000000;
    }

    if ($timedOut) {
      throw new \Exception('Operation timed out');
    }

    // Check if there were any errors.
    $errors = stream_get_contents($pipes[2]);

    if (!empty($errors)) {
      throw new \Exception($errors);
    }

    // Kill the process in case the timeout expired and it's still running.
    // If the process already exited this won't do anything.
//    $this->logger->debug('Terminating process');
    proc_terminate($process, 9);
//    $this->logger->debug('Process terminated');

    // Close all streams.
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

//    $this->logger->debug('Pipes closed');
    proc_close($process);

//    $this->logger->debug('Process closed');

    return $buffer;
  }

  /**
   * {@inheritdoc}
   */
  public static function killStalledProcesses() {
    // @todo This only seems to work on Debian-based systems.
    shell_exec('killall --older-than 2m phantomjs');
  }

}
