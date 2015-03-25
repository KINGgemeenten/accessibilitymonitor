<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|WappalyzerTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\PhantomJsInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a Wappalyzer tester.
 */
class WappalyzerTester implements TesterInterface
{

    /**
     * The Phantom JS manager.
     *
     * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface
     */
    protected $phantomJs;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\PhantomJsInterface
     */
    public function __construct(PhantomJsInterface $phantomJs)
    {
        $this->phantomJs = $phantomJs;
    }

    public function run(Url $url)
    {
        if ($url->isRoot()) {
            $url->setCms(implode('|', array_filter($this->phantomJs->getDetectedApps($url->getUrl()))));
        }
        return true;
    }

}
