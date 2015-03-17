<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Console\Command\StartWorker.
 */

namespace Triquanta\AccessibilityMonitor\Console\Command;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\ContainerFactoryInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Testing\TesterInterface;

/**
 * Provides a command to start a worker.
 */
class StartWorker extends Command implements ContainerFactoryInterface
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $queue;

    /**
     * The name of the queue.
     *
     * @var string
     */
    protected $queueName;

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface
     */
    protected $resultStorage;

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
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $queue
     * @param string $queueName
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      AMQPStreamConnection $queue,
      $queueName
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->queue = $queue;
        $this->queueName = $queueName;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static($container->get('logger'),
          $container->get('testing.tester'),
          $container->get('testing.result_storage'),
          $container->get('queue'),
          $container->getParameter('queue.name'));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('start-worker');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueChannel = $this->queue->channel();
        $properties = new AMQPTable();
        $properties->set('x-max-priority', 9);
        $queueChannel->queue_declare($this->queueName, false, true, false, false, false, $properties);
        $queueChannel->basic_qos(null, 1, null);
        $queueChannel->basic_consume($this->queueName, '', false, false, false, false, [$this, 'processMessage']);
        while(count($queueChannel->callbacks)) {
            $queueChannel->wait();
        }
    }

    /**
     * Processes a queue message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function processMessage(AMQPMessage $message) {
        // Check whether the message format is correct.
        if (preg_match('/^\d+$/', $message->body)) {
            $urlId = $message->body;
            $url = $this->resultStorage->getUrlById($urlId);

            if ($url) {
                $this->logger->info(sprintf('Testing %s.',
                  $url->getUrl()));

                $start = microtime(true);
                $result = $this->tester->run($url);
                $end = microtime(true);

                if ($result) {
                    $duration = $end - $start;
                    $this->logger->info(sprintf('Done testing (%s seconds)',
                      $duration));
                } // If the URL was not tested, add it back to the queue and stop.
                else {
                    $message->delivery_info['channel']->basic_publish($message,
                      '', $this->queueName);
                    $this->logger->info('Skipped testing.');
                }
            }
            else {
                $this->logger->emergency(sprintf('URL %s does not exist.', $urlId));
            }
        }
        else {
            $this->logger->emergency(sprintf('"%s" is not a valid URL ID.', $message->body));
        }
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

}
