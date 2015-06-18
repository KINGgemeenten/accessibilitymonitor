<?php
namespace Triquanta\AccessibilityMonitor;


/**
 * Class StatsD
 * @package Triquanta\AccessibilityMonitor
 */
interface StatsDInterface
{
    /**
     * increments the key by 1
     *
     * @param string $key
     * @param string $group
     *  Group name added to the metric
     * @param int $sampleRate
     */
    public function increment($key, $group = '', $sampleRate = 1);

    /**
     * starts the timing for a key
     *
     * @param string $key
     *  Metric name
     * @param string $group
     *  Group name added to the metric
     *
     */
    public function startTiming($key, $group = '');

    /**
     * ends the timing for a key and sends it to statsd
     *
     * @param string $key
     * @param string $group
     *  Group name added to the metric
     * @param int $sampleRate (optional)
     *
     * @return float|null
     */
    public function endTiming($key, $group = '', $sampleRate = 1);

    /**
     * sends a gauge, an arbitrary value to StatsD
     *
     * @param string $key
     * @param string|int $value
     * @param string $group
     */
    public function gauge($key, $value, $group = '');

}
