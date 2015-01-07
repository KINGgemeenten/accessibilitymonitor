<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\GooglePagespeed.
 */

namespace Triquanta\AccessibilityMonitor;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Provides a Google Pagespeed tester.
 */
class GooglePagespeed implements GooglePagespeedInterface {

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
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param string $api_url
   * @param string $api_key
   * @param string $api_strategy
   */
  public function __construct(LoggerInterface $logger, ClientInterface $http_client, $api_url, $api_key, $api_strategy) {
    $this->apiKey = $api_key;
    $this->apiStrategy = $api_strategy;
    $this->apiUrl = $api_url;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function test($url) {
    try {
      $request = $this->httpClient->createRequest('GET', $this->apiUrl);
      $request->getQuery()->set('key', $this->apiKey);
      $request->getQuery()->set('url', $url);
      $request->getQuery()->set('strategy', $this->apiStrategy);
      $request->setHeader('User-Agent', 'GT inspector script');
      $response = $this->httpClient->send($request);

      return json_decode((string) $response->getBody());
    }
    catch (ClientException $e) {
      $this->logger->emergency($e->getMessage());
      return array();
    }
  }

}
