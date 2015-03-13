<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\PurgeSolr.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Solarium\Core\Client\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;

/**
 * Provides a command to purge Solr caches.
 */
class PurgeSolr extends Command implements ContainerFactoryInterface
{

    /**
     * The Solr client.
     *
     * @var \Solarium\Core\Client\Client
     */
    protected $solrClient;

    /**
     * Constructs a new instance.
     *
     * @param \Solarium\Core\Client\Client $solr_client
     */
    public function __construct(Client $solr_client)
    {
        parent::__construct();
        $this->solrClient = $solr_client;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        // @todo Should this command really only purge one core and not both?
        return new static($container->get('solr.client.phantom'));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('solr-purge');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $update = $this->solrClient->createUpdate();
        $solrQuery = '*:*';
        $update->addDeleteQuery($solrQuery);
        $update->addCommit();
        $this->solrClient->update($update);
        $output->writeln('<info>The Solr core has been purged.</info>');
    }

}
