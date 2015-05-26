<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\PhantomJs.
 */

namespace Triquanta\AccessibilityMonitor;

use Psr\Log\LoggerInterface;

/**
 * Provides a Phantom JS manager.
 */
class PhantomJs implements PhantomJsInterface
{

    /**
     * The path to the Phantom JS executable.
     *
     * @var string
     */
    protected $executable;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
     * The path to the tmp directory.
     *
     * @var string
     */
    protected $tmpDirectory;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param string $executable
     * @param int $timeout
     * @param string $root_directory
     * @param string $tmpDirectory
     *   The path to the tmp directory.
     */
    public function __construct(
      LoggerInterface $logger,
      $executable,
      $timeout,
      $root_directory,
      $tmpDirectory
    ) {
        $this->executable = $executable;
        $this->logger = $logger;
        $this->rootDirectory = $root_directory;
        $this->timeout = $timeout;
        $this->tmpDirectory = $tmpDirectory;
    }

    public function getDetectedApps($url)
    {
        $this->killStalledProcesses();

        // Add http, if not present.
        if (!preg_match('/^http/', $url)) {
            $url = 'http://' . $url;
        }
        $command = $this->executable . ' --ignore-ssl-errors=yes --ssl-protocol=any ' . $this->rootDirectory . '/node_modules/phantalyzer/phantalyzer.js ' . $url;
        $output = shell_exec(escapeshellcmd($command));
        $preg_split = preg_split("/((\r?\n)|(\r\n?))/", $output);
        $detectedAppsArray = array();
        foreach ($preg_split as $line) {
            // Check the line detectedApps.
            if ($line != '' && preg_match("/^detectedApps/", $line)) {
                $detectedApps = str_replace('detectedApps: ', '', $line);
                $detectedAppsArray = explode('|', $detectedApps);
            }
            // Also check the generator.
            if ($line != '' && preg_match('/<meta name="generator" content="([A-Za-z\s]*)"/',
                $line, $matches)
            ) {
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

    public function killStalledProcesses()
    {
        // @todo This only seems to work on Debian-based systems.
        shell_exec('killall --older-than 2m phantomjs');
    }

    public function getQuailResult($url)
    {
        $this->killStalledProcesses();

        do {
            $testResultsDirectory = $this->tmpDirectory . '/quail_results-' . sha1(mt_rand());
        }
        while (file_exists($testResultsDirectory));
        if (!mkdir($testResultsDirectory, 0700, true)) {
            $this->logger->emergency(sprintf('Cannot create directory %s.', $testResultsDirectory));
            return json_encode(new \stdClass());
        }

        $command = sprintf('/opt/quail/bin/quail -R wcag2 -u "%s" -o %s', $url, $testResultsDirectory);
        $this->logger->debug('Starting phantomjs');
        try {
            $this->execTimeout($command, $this->timeout);
            $this->logger->debug('Phantomjs executed succesfully.');

            $files = glob($testResultsDirectory . '/*.js');
            if ($files) {
                $file = reset($files);
                $results = file_get_contents($file);
            }
            else {
                $results = json_encode(new \stdClass());
            }
            return $results;
        }
        finally {
            $files = glob($testResultsDirectory . '/*.js');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($testResultsDirectory);
        }
    }

    /**
     * Executes a shell command with a timeout.
     *
     * This function is based on the following solution:
     * http://blog.dubbelboer.com/2012/08/24/execute-with-timeout.html
     *
     * @param string $cmd
     *   Command to execute.
     * @param int $timeout
     *   Timeout in seconds.
     *
     * @return string Output of the command.
     *
     * @throws \Exception
     */
    protected function execTimeout($cmd, $timeout)
    {
        $timeout = 1;
        $timedOut = true;
        // File descriptors passed to the process.
        $descriptors = array(
          0 => array('pipe', 'r'),  // stdin
          1 => array('pipe', 'w'),  // stdout
          2 => array('pipe', 'w')   // stderr
        );

        $this->logger->debug('Openining processes');
        // Start the process.
        $process = proc_open(escapeshellcmd('exec ' . $cmd), $descriptors,
          $pipes);

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
            $read = array($pipes[1]);
            $other = array();
            stream_select($read, $other, $other, 0, $timeout);

            // Get the status of the process.
            // Do this before we read from the stream,
            // this way we can't lose the last bit of output if the process dies between these functions.
            $status = proc_get_status($process);

            // Read the contents from the buffer.
            // This function will always return immediately as the stream is none-blocking.
            $buffer .= stream_get_contents($pipes[1]);
            $this->logger->debug('Buffer filled');

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
        $this->logger->debug('Terminating process');
        proc_terminate($process, 9);
        $this->logger->debug('Process terminated');

        // Close all streams.
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->logger->debug('Pipes closed');
        proc_close($process);

        $this->logger->debug('Process closed');

        return $buffer;
    }

}
