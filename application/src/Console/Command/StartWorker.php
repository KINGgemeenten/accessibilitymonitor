<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\StartWorker.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\WorkerInterface;

/**
 * Provides a command to start a worker.
 */
class StartWorker extends Command implements ContainerFactoryInterface
{

    /**
     * The worker.
     *
     * @var \Triquanta\AccessibilityMonitor\WorkerInterface
     */
    protected $worker;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\WorkerInterface $worker
     */
    public function __construct(
        WorkerInterface $worker
    ) {
        parent::__construct();
        $this->worker = $worker;
    }

    public static function create(ContainerInterface $container)
    {
        return new static($container->get('worker'));
    }

    protected function configure()
    {
        $this->setName('start-worker');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->worker->registerWorker();
    }

}
