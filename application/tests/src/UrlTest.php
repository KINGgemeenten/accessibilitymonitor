<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\UrlTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface;
use Triquanta\AccessibilityMonitor\Url;;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Url
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Url
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->sut = new Url();
    }

    /**
     * @covers ::setId
     * @covers ::getId
     */
    public function testGetId() {
        $id = mt_rand();
        $this->assertSame($this->sut, $this->sut->setId($id));
        $this->assertSame($id, $this->sut->getId());
    }

    /**
     * @covers ::setId
     *
     * @depends testGetId
     *
     * @expectedException \BadMethodCallException
     */
    public function testChangeId() {
        $id = mt_rand();
        $this->sut->setId($id);
        $this->sut->setId($id);
    }

    /**
     * @covers ::setUrl
     * @covers ::getUrl
     */
    public function testGetUrl() {
        $url = 'http://foo.example.com/' . mt_rand();
        $this->assertSame($this->sut, $this->sut->setUrl($url));
        $this->assertSame($url, $this->sut->getUrl());
    }

    /**
     * @covers ::setUrl
     *
     * @depends testGetUrl
     *
     * @expectedException \BadMethodCallException
     */
    public function testChangeUrl() {
        $url = 'http://foo.example.com/' . mt_rand();
        $this->sut->setUrl($url);
        $this->sut->setUrl($url);
    }

    /**
     * @covers ::setUrl
     *
     * @depends testGetUrl
     *
     * @expectedException \InvalidArgumentException
     */
    public function testSetUrlWithInvalidUrl() {
        $url = 'http://foo.example_com/' . mt_rand();
        $this->sut->setUrl($url);
    }

    /**
     * @covers ::getMainDomain
     *
     * @depends testGetUrl
     */
    public function testGetMainDomain() {
        $url = 'http://example.com/' . mt_rand();
        $this->sut->setUrl($url);
        $this->assertSame('example.com', $this->sut->getMainDomain());
    }

    /**
     * @covers ::getMainDomain
     *
     * @depends testGetUrl
     */
    public function testGetMainDomainWithSubdomain() {
        $url = 'http://foo.example.com/' . mt_rand();
        $this->sut->setUrl($url);
        $this->assertSame('example.com', $this->sut->getMainDomain());
    }

    /**
     * @covers ::getHostName
     *
     * @depends testGetUrl
     */
    public function testGetHostName() {
        $url = 'http://foo.example.com/' . mt_rand();
        $this->sut->setUrl($url);
        $this->assertSame('foo.example.com', $this->sut->getHostName());
    }

    /**
     * @covers ::setTestingStatus
     * @covers ::getTestingStatus
     */
    public function testGetTestingStatus() {
        $status = array_rand([TestingStatusInterface::STATUS_TESTED, TestingStatusInterface::STATUS_SCHEDULED_FOR_RETEST, TestingStatusInterface::STATUS_SCHEDULED, TestingStatusInterface::STATUS_ERROR]);
        $this->assertSame($this->sut, $this->sut->setTestingStatus($status));
        $this->assertSame($status, $this->sut->getTestingStatus());
    }

    /**
     * @covers ::setCms
     * @covers ::getCms
     */
    public function testGetCms() {
        $cms = sprintf('Drupal %d.%d', mt_rand(), mt_rand());
        $this->assertSame($this->sut, $this->sut->setCms($cms));
        $this->assertSame($cms, $this->sut->getCms());
    }

    /**
     * @covers ::setQuailResult
     * @covers ::getQuailResult
     */
    public function testGetQuailResult() {
        $result = json_encode([
          'foo' => mt_rand(),
        ]);
        $this->assertSame($this->sut, $this->sut->setQuailResult($result));
        $this->assertSame($result, $this->sut->getQuailResult());
    }

    /**
     * @covers ::setQuailResultCases
     * @covers ::getQuailResultCases
     */
    public function testGetQuailResultCases() {
        $cases = [
          'foo' => mt_rand(),
        ];
        $this->assertSame($this->sut, $this->sut->setQuailResultCases($cases));
        $this->assertSame($cases, $this->sut->getQuailResultCases());
    }

    /**
     * @covers ::setGooglePageSpeedResult
     * @covers ::getGooglePageSpeedResult
     */
    public function testGetGooglePageSpeedResult() {
        $result = json_encode([
          'foo' => mt_rand(),
        ]);
        $this->assertSame($this->sut, $this->sut->setGooglePageSpeedResult($result));
        $this->assertSame($result, $this->sut->getGooglePageSpeedResult());
    }

    /**
     * @covers ::setLastProcessedTime
     * @covers ::getLastProcessedTime
     */
    public function testGetLastProcessedTime() {
        $time = mt_rand();
        $this->assertSame($this->sut, $this->sut->setLastProcessedTime($time));
        $this->assertSame($time, $this->sut->getLastProcessedTime());
    }

    /**
     * @covers ::setRoot
     * @covers ::isRoot
     */
    public function testIsRoot() {
        $isRoot = (bool) mt_rand(0, 1);
        $this->assertSame($this->sut, $this->sut->setRoot($isRoot));
        $this->assertSame($isRoot, $this->sut->isRoot());
    }

    /**
     * @covers ::setTestRunId
     * @covers ::getTestRunId
     */
    public function testGetTestRunId() {
        $id = mt_rand();
        $this->assertSame($this->sut, $this->sut->setTestRunId($id));
        $this->assertSame($id, $this->sut->getTestRunId());
    }

    /**
     * @covers ::setFailedTestCount
     * @covers ::getFailedTestCount
     */
    public function testGetFailedTestCount() {
        $count = mt_rand();
        $this->assertSame($this->sut, $this->sut->setFailedTestCount($count));
        $this->assertSame($count, $this->sut->getFailedTestCount());
    }

}
