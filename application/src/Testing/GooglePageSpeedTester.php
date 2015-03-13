<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|GooglePageSpeedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
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
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \GuzzleHttp\ClientInterface
     * @param string $apiKey
     * @param string $apiUrl
     * @param string $apiStrategy
     */
    public function __construct(
      LoggerInterface $logger,
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
    }

    public function run(Url $url)
    {
        if ($url->isRoot()) {
            try {
                $request = $this->httpClient->createRequest('GET',
                  $this->apiUrl);
                $request->getQuery()->set('key', $this->apiKey);
                $request->getQuery()->set('locale', 'nl');
                $request->getQuery()->set('url', $url->getUrl());
                $request->getQuery()->set('strategy', $this->apiStrategy);
                $request->setHeader('User-Agent', 'GT inspector script');

                $response = $this->httpClient->send($request);

                $url->setGooglePageSpeedResult($response->getBody()
                  ->getContents());
            } catch (ClientException $e) {
                $this->logger->emergency($e->getMessage());
            }
        }
    }

}
