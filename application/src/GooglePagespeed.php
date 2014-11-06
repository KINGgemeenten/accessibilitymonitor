<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\GooglePagespeed.
 */

namespace Triquanta\AccessibilityMonitor;

use GuzzleHttp\ClientInterface;

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
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param string $api_url
   * @param string $api_key
   * @param string $api_strategy
   */
  public function __construct(ClientInterface $http_client, $api_url, $api_key, $api_strategy) {
    $this->apiKey = $api_key;
    $this->apiStrategy = $api_strategy;
    $this->apiUrl = $api_url;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function test($url) {
    $request = $this->httpClient->createRequest('GET', $this->apiUrl);
    $request->getQuery()->set('key', $this->apiKey);
    $request->getQuery()->set('url', $url);
    $request->getQuery()->set('strategy', $this->apiStrategy);
    $request->setHeader('User-Agent', 'GT inspector script');
    $response = $this->httpClient->send($request);

    return json_decode((string) $response->getBody());
  }

}
