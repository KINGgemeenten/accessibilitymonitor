<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\AddWebsite.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ActionsInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\Website;

/**
 * Provides a command to schedule a website for testing.
 */
class AddWebsite extends Command implements ContainerFactoryInterface {

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface
   */
  protected $actions;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\ActionsInterface $actions
   */
  public function __construct(ActionsInterface $actions) {
    parent::__construct();
    $this->actions = $actions;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('actions'));
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('website-add')
      ->addArgument('url', InputArgument::REQUIRED);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $website = new Website();
    $website->setUrl($input->getArgument('url'));
    $this->actions->addWebsite($website);
  }

}
