<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\UpdateSiteList.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Solarium\Core\Client\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a command to detect a CMS.
 */
class UpdateSiteList extends Command implements ContainerFactoryInterface {

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client
   */
  protected $solrClient;

  /**
   * The number of URLs to process per batch.
   *
   * @var int
   */
  protected $urlsPerSample;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Solarium\Core\Client\Client $solr_client
   * @param int $urls_per_sample
   *   The number of URLs to process per batch.
   */
  public function __construct(StorageInterface $storage, Client $solr_client, $urls_per_sample) {
    parent::__construct();
    $this->storage = $storage;
    $this->solrClient = $solr_client;
    $this->urlsPerSample = $urls_per_sample;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('storage'), $container->get('solr.client.nutch'), $container->getParameter('urls_per_sample'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('update-sitelist');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $toBeTested = $this->storage->getWebsitesByStatuses(array(Url::STATUS_SCHEDULED, Url::STATUS_TESTED));
    $output->writeln(sprintf('<info>%d websites to be tested.</info>', count($toBeTested)));

    foreach ($toBeTested as $website) {
      // Now check if there are still url's to be tested for this website.
      // If not, try to get more from solr..
      $count = $this->storage->countUrlsByStatusAndWebsiteId(Url::STATUS_SCHEDULED, $website->getId());
      $output->writeln(sprintf('<info>%d documents to be tested for %s.</info>', $count, $website->getUrl()));
      if ($count == 0) {
        // Get the total amount url's so we can define a start for Solr.
        $start = $this->storage->countUrlsByWebsiteId($website->getId());

        // Create a query.
        $query = $this->solrClient->createSelect();

        // Set some query parameters.
        $query->addParam('defType', 'edismax');
        $query->addParam('qf', 'host^0.001 url^2');
        $query->addParam('df', 'host');

        // Add the filter.
//        $baseUrl = str_replace('www.', '', $entry->url);
        // Get the host of the url.
        $parts = parse_url($website->getUrl());
        if (isset($parts['host'])) {
          $host = $parts['host'];
//        $query->setQuery('host:' . $host);
          $query->setQuery($host);

          // Add a filter application type, so we only have html and no pdf's!
          $type_query = 'type:application/xhtml+xml OR type:text/html';
          $query->createFilterQuery('type')->setQuery($type_query);

          // Now also add a filter query for host.
          $host_query = 'host:"' . $host . '"';
          $query->createFilterQuery('host')->setQuery($host_query);

          // Set the fields.
          $query->setFields(array('url', 'score'));

          // Set the rows.
          $query->setRows($this->urlsPerSample);
          // Set the start
          $query->setStart($start);

          // Get the results.
          $solrResults = $this->solrClient->select($query);

          // Set the priority to 1, for the first document and increase.
          $priority = 1;
          foreach ($solrResults as $doc) {
            // Check if entry already exists.
            $present = $this->storage->countUrlsByWebsiteIdAndFullUrl($website->getId(), $doc->url);

            if (!$present) {
              $url = new Url();
              $url->setWebsiteId($website->getId())
                ->setUrl($doc->url)
                ->setTestingStatus($url::STATUS_SCHEDULED)
                ->setPriority($priority);
              $this->storage->saveUrl($url);
              $output->writeln(sprintf('<info>Queued %s.</info>', $doc->url));
              // Increase the priority.
              $priority++;
            }
          }
        }
      }
    }
    $output->writeln('<info>Done.</info>');
  }

}
