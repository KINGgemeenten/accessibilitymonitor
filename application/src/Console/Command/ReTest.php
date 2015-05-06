<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\ReTest.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\Testing\ReTester;

/**
 * Provides a command to to re-test URLs.
 */
class ReTest extends Command implements ContainerFactoryInterface
{

    /**
     * The re-tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\ReTester
     */
    protected $reTester;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\Testing\ReTester $reTester
     */
    public function __construct(
      ReTester $reTester
    ) {
        parent::__construct();
        $this->reTester = $reTester;
    }

    public static function create(ContainerInterface $container)
    {
        return new static($container->get('testing.retester'));
    }

    protected function configure()
    {
        $this->setName('retest');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->reTester->retest();
    }

}
