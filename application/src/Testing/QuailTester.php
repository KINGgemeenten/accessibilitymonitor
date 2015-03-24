<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|QuailTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\PhantomJsInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a Quail tester.
 */
class QuailTester implements TesterInterface
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The Phantom JS manager.
     *
     * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface
     */
    protected $phantomJs;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\PhantomJsInterface $phantomJs
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
      PhantomJsInterface $phantomJs,
      LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->phantomJs = $phantomJs;
    }

    public function run(Url $url)
    {
        try {
            $result = $this->phantomJs->getQuailResult($url->getUrl());
            $this->processQuailResult($url, $result);
            if ($url->getQuailResult()) {
                $url->setTestingStatus(Url::STATUS_TESTED);
                $url->setAnalysis(time());
                return true;
            }
            else {
                $this->logger->info(sprintf('No Quail results when testing %s.', $url->getUrl()));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
            return false;
        }
    }

    /**
     * @param \Triquanta\AccessibilityMonitor\Url $url
     */
    protected function processQuailResult(Url $url, $result)
    {
        $quailFinalResults = array();
        // Store the quailCases in a temporary array.
        $quailCases = array();
        $wcag20Mapping = $this->getWcag2Mapping();
        $rawResult = json_decode($result);
        foreach ($rawResult as $criterium) {
            $criteriumNumber = $wcag20Mapping[$criterium->testRequirement];
            // Extract the most important cases.
            // First check if there is a pointer, because if there is none,
            // the cases doesn't need to be stored.
            if (property_exists($criterium,
                'hasPart') && count($criterium->hasPart)
            ) {
                foreach ($criterium->hasPart as $case) {
                    // Check if there is a pointer in the outcome.
                    // The pointer contains the html snippet we need.
                    if (isset($case->outcome->pointer) && isset($case->outcome->pointer[0]->chars) && ($case->outcome->result == 'failed' || $case->outcome->result == 'cantTell')) {
                        // Create the unique key, to prevent that we store only one case per testCase per criterium.
                        $uniqueKey = str_replace('.', '_',
                            $criteriumNumber) . '_' . $case->testCase . '_' . $case->outcome->result;
                        if (!isset ($quailCases[$uniqueKey])) {
                            // Add the unique key to the case.
                            $case->uniqueKey = $uniqueKey;
                            // Add the criteriumName to the case.
                            $case->criteriumName = $criteriumNumber;
                            $quailCases[$uniqueKey] = $case;
                        }
                    }
                }
            }
            // Now unset the hasPart, on order to save space.
            unset($criterium->hasPart);
            // Add criterium.
            $criterium->criterium = $criteriumNumber;
            $quailFinalResults[$criteriumNumber] = $criterium;
        }
        $url->setQuailResult($quailFinalResults);
        $url->setQuailResultCases($quailCases);

    }

    /**
     * Get the wcag2 mapping array.
     *
     * This would be better as static variable, but that doesn't work with threads.
     *
     * @return array
     */
    protected function getWcag2Mapping()
    {
        $wcag20Mapping = [
          "wcag20:text-equiv-all" => "1.1.1",
          "wcag20:media-equiv-av-only-alt" => "1.2.1",
          "wcag20:media-equiv-captions" => "1.2.2",
          "wcag20:media-equiv-audio-desc" => "1.2.3",
          "wcag20:media-equiv-real-time-captions" => "1.2.4",
          "wcag20:media-equiv-audio-desc-only" => "1.2.5",
          "wcag20:media-equiv-sign" => "1.2.6",
          "wcag20:media-equiv-extended-ad" => "1.2.7",
          "wcag20:media-equiv-text-doc" => "1.2.8",
          "wcag20:media-equiv-live-audio-only" => "1.2.9",
          "wcag20:content-structure-separation-programmatic" => "1.3.1",
          "wcag20:content-structure-separation-sequence" => "1.3.2",
          "wcag20:content-structure-separation-understanding" => "1.3.3",
          "wcag20:visual-audio-contrast-without-color" => "1.4.1",
          "wcag20:visual-audio-contrast-dis-audio" => "1.4.2",
          "wcag20:visual-audio-contrast-contrast" => "1.4.3",
          "wcag20:visual-audio-contrast-scale" => "1.4.4",
          "wcag20:visual-audio-contrast-text-presentation" => "1.4.5",
          "wcag20:visual-audio-contrast7" => "1.4.6",
          "wcag20:visual-audio-contrast-noaudio" => "1.4.7",
          "wcag20:visual-audio-contrast-visual-presentation" => "1.4.8",
          "wcag20:visual-audio-contrast-text-images" => "1.4.9",
          "wcag20:keyboard-operation-keyboard-operable" => "2.1.1",
          "wcag20:keyboard-operation-trapping" => "2.1.2",
          "wcag20:keyboard-operation-all-funcs" => "2.1.3",
          "wcag20:time-limits-required-behaviors" => "2.2.1",
          "wcag20:time-limits-pause" => "2.2.2",
          "wcag20:time-limits-no-exceptions" => "2.2.3",
          "wcag20:time-limits-postponed" => "2.2.4",
          "wcag20:time-limits-server-timeout" => "2.2.5",
          "wcag20:seizure-does-not-violate" => "2.3.1",
          "wcag20:seizure-three-times" => "2.3.2",
          "wcag20:navigation-mechanisms-skip" => "2.4.1",
          "wcag20:navigation-mechanisms-title" => "2.4.2",
          "wcag20:navigation-mechanisms-focus-order" => "2.4.3",
          "wcag20:navigation-mechanisms-refs" => "2.4.4",
          "wcag20:navigation-mechanisms-mult-loc" => "2.4.5",
          "wcag20:navigation-mechanisms-descriptive" => "2.4.6",
          "wcag20:navigation-mechanisms-focus-visible" => "2.4.7",
          "wcag20:navigation-mechanisms-location" => "2.4.8",
          "wcag20:navigation-mechanisms-link" => "2.4.9",
          "wcag20:navigation-mechanisms-headings" => "2.4.10",
          "wcag20:meaning-doc-lang-id" => "3.1.1",
          "wcag20:meaning-other-lang-id" => "3.1.2",
          "wcag20:meaning-idioms" => "3.1.3",
          "wcag20:meaning-located" => "3.1.4",
          "wcag20:meaning-supplements" => "3.1.5",
          "wcag20:meaning-pronunciation" => "3.1.6",
          "wcag20:consistent-behavior-receive-focus" => "3.2.1",
          "wcag20:consistent-behavior-unpredictable-change" => "3.2.2",
          "wcag20:consistent-behavior-consistent-locations" => "3.2.3",
          "wcag20:consistent-behavior-consistent-functionality" => "3.2.4",
          "wcag20:consistent-behavior-no-extreme-changes-context" => "3.2.5",
          "wcag20:minimize-error-identified" => "3.3.1",
          "wcag20:minimize-error-cues" => "3.3.2",
          "wcag20:minimize-error-suggestions" => "3.3.3",
          "wcag20:minimize-error-reversible" => "3.3.4",
          "wcag20:minimize-error-context-help" => "3.3.5",
          "wcag20:minimize-error-reversible-all" => "3.3.6",
          "wcag20:ensure-compat-parses" => "4.1.1",
          "wcag20:ensure-compat-rsv" => "4.1.2"
        ];

        return $wcag20Mapping;
    }

}
