<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\ApplicationTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Triquanta\AccessibilityMonitor\Application;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Application
 */
class ApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::getContainer
     * @covers ::setContainer
     */
    function testGetContainer()
    {
        $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');

        $this->assertNull(Application::getContainer());
        Application::setContainer($container);
        $this->assertSame($container, Application::getContainer());
    }

    /**
     * @covers ::bootstrap
     *
     * @depends testGetContainer
     */
    function testBootstrap()
    {
        Application::bootstrap();
        $this->assertInstanceOf('\Symfony\Component\DependencyInjection\ContainerInterface',
          Application::getContainer());
    }

}
