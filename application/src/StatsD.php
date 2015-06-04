<?php

namespace Triquanta\AccessibilityMonitor;

use Domnikl\Statsd\Client;
use Domnikl\Statsd\Connection\Blackhole;
use Domnikl\Statsd\Connection\UdpSocket;


/**
 * Class StatsD
 * @package Triquanta\AccessibilityMonitor
 */
class StatsD implements StatsDInterface
{

    /**
     * @var \Domnikl\Statsd\Client
     */
    protected $client;

    /**
     * @param string $hostname
     *  Hostname of the StatsD server
     * @param int $port
     *  Port of the statsD server
     * @param string $connection_type
     *  Currently only UdpSocket and Blackhole are supported.
     * @param string $namespace
     *  Metric prefix
     *
     * @throws \InvalidArgumentException
     *
     */
    function __construct($hostname, $port, $connection_type, $namespace)
    {
        switch ($connection_type) {
            case 'UdpSocket':

                $connection = new UdpSocket($hostname, $port);
                break;
            case 'Blackhole':
                $connection = new Blackhole();
                break;
            default:
                throw new \InvalidArgumentException('No valid connection type given.');
                break;
        }

        $this->client = new Client($connection, $namespace);
    }

    public function increment($key, $group = '', $sampleRate = 1)
    {
        $this->client->count($this->decorateMetricName($key, $group), 1, $sampleRate);
    }

    public function startTiming($key, $group = '')
    {
        $this->client->startTiming($this->decorateMetricName($key, $group));
    }

    public function endTiming($key, $group = '', $sampleRate = 1)
    {
        return $this->client->endTiming($this->decorateMetricName($key, $group), $sampleRate);
    }

    public function gauge($key, $value, $group = '')
    {
        $this->client->gauge($this->decorateMetricName($key, $group), $value);
    }

    /**
     * Helper function to cleanup metric names
     * Replace non alphanumeric characters for an underscore
     * (except for underscores and dashes)
     *
     * @param string $metric_name
     *  The group name
     * @return mixed
     *  Sanatized metric name
     */
    protected function sanitizeMetricName($metric_name) {
        return preg_replace('/[^\da-z_-]/i', '_', $metric_name);
    }

    /**
     * Helper function to decorate metric names width:
     * - Group name
     *
     * @param string $key
     *  The metric name
     * @param string $group
     *  The metric name
     * @return mixed
     *  Decorated metric name
     */
    protected function decorateMetricName($key, $group) {
        if (!empty($group)) {
            $decorated_key = 'group.' . $this->sanitizeMetricName($group) . "." . $key;
        }
        else {
            $decorated_key = 'no_group.' . $key;
        }
        return $decorated_key;
    }
}
