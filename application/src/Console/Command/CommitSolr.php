<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\CommitSolr.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Solarium\Core\Client\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;

/**
 * Provides a command to commit documents to Solr.
 */
class CommitSolr extends Command implements ContainerFactoryInterface
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
        return new static($container->get('solr.client.phantom'));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        // @todo Give this command a more descriptive name based on what it is supposed to achieve.
        $this->setName('solr-commit');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $update = $this->solrClient->createUpdate();
        $update->addCommit();
        $this->solrClient->update($update);
        $output->writeln('<info>An empty commit has been sent to the Solr core.</info>');
    }

}
