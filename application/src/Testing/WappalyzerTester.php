<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|WappalyzerTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\PhantomJsInterface;
use Triquanta\AccessibilityMonitor\StatsDInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a Wappalyzer tester.
 */
class WappalyzerTester implements TesterInterface
{
    /**
     * The StatsD logger.
     *
     * @var \Triquanta\AccessibilityMonitor\StatsD
     */
    protected $statsD;

    /**
     * The Phantom JS manager.
     *
     * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface
     */
    protected $phantomJs;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\StatsDInterface $statsD
     * @param \Triquanta\AccessibilityMonitor\PhantomJsInterface
     */
    public function __construct(
      StatsDInterface $statsD,
      PhantomJsInterface $phantomJs
    )
    {
        $this->statsD = $statsD;
        $this->phantomJs = $phantomJs;
    }

    public function run(Url $url)
    {
        if ($url->isRoot()) {
            $this->statsD->startTiming("tests.wappalyzer.duration");

            try {
                $url->setCms(implode('|',
                    array_filter($this->phantomJs->getDetectedApps($url->getUrl()))));
            }
            catch (\Exception $e) {
                throw $e;
            }
            finally {
                $this->statsD->increment("tests.wappalyzer.count");
                $this->statsD->endTiming("tests.wappalyzer.duration");
            }
        }
        return true;
    }

}
