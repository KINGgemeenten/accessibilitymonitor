<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\Check.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\Testing\TesterInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a command to check a URL.
 */
class Check extends Command implements ContainerFactoryInterface
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface
     */
    protected $tester;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->tester = $tester;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static($container->get('logger'),
          $container->get('testing.tester.grouped'));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('check')
          ->addArgument('url', InputArgument::REQUIRED);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = new Url();
        $url->setRoot(true);
        $url->setUrl($input->getArgument('url'));

        $this->logger->info(sprintf('Testing %s. The result will not be stored.',
          $url->getUrl()));

        $start = microtime(true);
        $this->tester->run($url);
        $end = microtime(true);

        // @todo Decide how to output the test results.

        $duration = $end - $start;
        $output->writeln('<info>Done testing (' . $duration . ' seconds)</info>');
    }

}
