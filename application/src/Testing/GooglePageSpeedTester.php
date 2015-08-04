<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|GooglePageSpeedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\StatsDInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a Google PageSpeed tester.
 */
class GooglePageSpeedTester implements TesterInterface
{

    /**
     * The API key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * The API strategy.
     *
     * @var string
     */
    protected $apiStrategy;

    /**
     * The API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The StatsD logger.
     *
     * @var \Triquanta\AccessibilityMonitor\StatsD
     */
    protected $statsD;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Triquanta\AccessibilityMonitor\StatsDInterface $statsD
     * @param \GuzzleHttp\ClientInterface
     * @param string $apiKey
     * @param string $apiUrl
     * @param string $apiStrategy
     */
    public function __construct(
      LoggerInterface $logger,
      StatsDInterface $statsD,
      ClientInterface $httpClient,
      $apiKey,
      $apiUrl,
      $apiStrategy
    ) {
        $this->apiKey = $apiKey;
        $this->apiStrategy = $apiStrategy;
        $this->apiUrl = $apiUrl;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->statsD = $statsD;
    }

    public function run(Url $url)
    {
        if ($url->isRoot()) {
            $this->statsD->startTiming("tests.google_pagespeed.duration");

            $request = $this->httpClient->createRequest('GET',
              $this->apiUrl);
            $request->getQuery()->set('key', $this->apiKey);
            $request->getQuery()->set('locale', 'en');
            $request->getQuery()->set('url', $url->getUrl());
            $request->getQuery()->set('strategy', $this->apiStrategy);
            $request->setHeader('User-Agent', 'GT inspector script');

            try {
                $response = $this->httpClient->send($request);
                $url->setGooglePageSpeedResult($response->getBody()
                  ->getContents());
            }
            catch (\Exception $e) {
                throw $e;
            }
            finally {
                $this->statsD->increment("tests.google_pagespeed.count");
                $this->statsD->endTiming("tests.google_pagespeed.duration");
            }
        }

        return true;
    }

}
