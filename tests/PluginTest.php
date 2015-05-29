<?php
/**
 * Phergie plugin for verifying that the client connection has not been dropped by sending self CTCP PINGs after periods of inactivity. (https://github.com/Renegade334/phergie-irc-plugin-react-ping)
 *
 * @link https://github.com/Renegade334/phergie-irc-plugin-react-ping for the canonical source repository
 * @copyright Copyright (c) 2015 Renegade334 (http://www.renegade334.me.uk/)
 * @license http://phergie.org/license Simplified BSD License
 * @package Renegade334\Phergie\Plugin\React\Ping
 */

namespace Renegade334\Phergie\Tests\Plugin\React\Ping;

use Phake;
use Phergie\Irc\ConnectionInterface;
use Renegade334\Phergie\Plugin\React\Ping\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Renegade334
 * @package Renegade334\Phergie\Plugin\React\Ping
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Mock logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Mock event loop.
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Mock event queue factory.
     *
     * @var \Phergie\Irc\Bot\React\EventQueueFactoryInterface
     */
    protected $queueFactory;

    /**
     * Common setup.
     */
    public function setUp()
    {
        $this->logger = Phake::mock('\Psr\Log\LoggerInterface');
        $this->loop = Phake::mock('\React\EventLoop\LoopInterface');
        $this->queueFactory = Phake::mock('\Phergie\Irc\Bot\React\EventQueueFactoryInterface');
    }

    /**
     * Creates an instance of the plugin to test.
     *
     * @param array $config
     * @return \Renegade334\Phergie\Plugin\React\Ping\Plugin
     */
    public function getPlugin(array $config = array())
    {
        $plugin = new Plugin($config);
        $plugin->setLoop($this->loop);
        $plugin->setLogger($this->logger);
        $plugin->setEventQueueFactory($this->queueFactory);
        return $plugin;
    }

    /**
     * Creates a mock connection object.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    public function getMockConnection()
    {
        $connection = Phake::mock('\Phergie\Irc\ConnectionInterface');
        Phake::when($connection)->getNickname()->thenReturn('BotNick');
        return $connection;
    }

    /**
     * Creates a mock IRC event.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return \Phergie\Irc\Event\EventInterface
     */
    public function getMockEvent(ConnectionInterface $connection)
    {
        $event = Phake::mock('\Phergie\Irc\Event\EventInterface');
        Phake::when($event)->getConnection()->thenReturn($connection);
        return $event;
    }

    /**
     * Returns a mock event queue.
     *
     * @return \Phergie\Irc\Bot\React\EventQueueInterface
     */
    public function getMockQueue()
    {
        return Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
    }

    /**
     * Returns a mock timer object.
     *
     * @return \React\EventLoop\Timer\TimerInterface
     */
    public function getMockTimer()
    {
        return Phake::mock('\React\EventLoop\Timer\TimerInterface');
    }

    /**
     * Data provider for testInvalidConfiguration.
     *
     * @return array
     */
    public function dataProviderInvalidConfiguration()
    {
        return array(
            array(
                array('wait' => 'invalid'),
            ),
            array(
                array('timeout' => -20),
            ),
        );
    }

    /**
     * Tests invalid configuration.
     *
     * @param array $config
     * @dataProvider dataProviderInvalidConfiguration
     * @expectedException \DomainException
     */
    public function testInvalidConfiguration(array $config)
    {
        $plugin = $this->getPlugin($config);
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $plugin = new Plugin;
        $this->assertInternalType('array', $plugin->getSubscribedEvents());
    }

    /**
     * Data provider for testHandleReceived.
     *
     * @return array
     */
    public function dataProviderHandleReceived()
    {
        return array(
            array(
                array(),
                90,
            ),

            array(
                array('wait' => 60),
                60,
            ),
        );
    }

    /**
     * Tests handleReceived()
     *
     * @param array $config
     * @param int $wait
     * @dataProvider dataProviderHandleReceived
     */
    public function testHandleReceived(array $config, $wait)
    {
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent($connection);
        $queue = $this->getMockQueue();

        $timer = $this->getMockTimer();
        Phake::when($this->loop)->addTimer->thenReturn($timer);

        $plugin = $this->getPlugin($config);

        $plugin->handleReceived($event, $queue);
        Phake::verify($this->loop)->addTimer($wait, array($plugin, 'callbackPhoneHome'));
        Phake::verify($timer)->setData($connection);
        $this->assertSame($timer, $plugin->getTimers()->offsetGet($connection));
    }

    /**
     * Tests that handleReceived() cancels an existing timer.
     */
    public function testHandleReceivedExistingTimer()
    {
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent($connection);
        $queue = $this->getMockQueue();

        $timer1 = $this->getMockTimer();
        $timer2 = $this->getMockTimer();
        
        Phake::when($this->loop)->addTimer->thenReturn($timer2);

        $plugin = $this->getPlugin();
        $timers = $plugin->getTimers();
        $timers->attach($connection, $timer1);

        $plugin->handleReceived($event, $queue);
        Phake::verify($timer1)->cancel();
        $this->assertSame($timer2, $timers->offsetGet($connection));
    }

    /**
     * Data provider for testPhoneHome.
     *
     * @return array
     */
    public function dataProviderPhoneHome()
    {
        return array(
            array(
                array(),
                20,
            ),
            
            array(
                array('timeout' => 10),
                10,
            ),
        );
    }

    /**
     * Tests callbackPhoneHome()
     *
     * @param array $config
     * @param int $timeout
     * @dataProvider dataProviderPhoneHome
     */
    public function testPhoneHome(array $config, $timeout)
    {
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent($connection);
        $queue = $this->getMockQueue();
        Phake::when($this->queueFactory)->getEventQueue($connection)->thenReturn($queue);

        $caller = $this->getMockTimer();
        Phake::when($caller)->getData()->thenReturn($connection);

        $timer = $this->getMockTimer();
        Phake::when($this->loop)->addTimer->thenReturn($timer);

        $plugin = $this->getPlugin($config);

        $plugin->callbackPhoneHome($caller);
        Phake::verify($this->loop)->addTimer($timeout, array($plugin, 'callbackGrimReaper'));
        Phake::verify($queue)->ctcpPing('BotNick', $this->anything());
        $this->assertSame($plugin->getTimers()->offsetGet($connection), $timer);
    }

    /**
     * Tests callbackGrimReaper()
     */
    public function testGrimReaper()
    {
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent($connection);
        $queue = $this->getMockQueue();
        Phake::when($this->queueFactory)->getEventQueue($connection)->thenReturn($queue);

        $caller = $this->getMockTimer();
        Phake::when($caller)->getData()->thenReturn($connection);

        $plugin = $this->getPlugin();

        $plugin->callbackGrimReaper($caller);
        Phake::verify($queue)->ircQuit();
    }

    /**
     * Tests handleDisconnect()
     */
    public function testHandleDisconnect()
    {
        $connection = $this->getMockConnection();
        $event = $this->getMockEvent($connection);
        $queue = $this->getMockQueue();
        Phake::when($this->queueFactory)->getEventQueue($connection)->thenReturn($queue);

        $timer = $this->getMockTimer();
        Phake::when($this->loop)->addTimer->thenReturn($timer);

        $plugin = $this->getPlugin();
        $timers = $plugin->getTimers();
        $timers->attach($connection, $timer);

        $plugin->handleDisconnect($connection, $this->logger);
        Phake::verify($timer)->cancel();
        $this->assertFalse($timers->contains($connection));
    }

    /**
     * Tests that only the correct connection is used by each callback.
     */
    public function testConnectionHandling()
    {
        $connection1 = $this->getMockConnection();
        $connection2 = $this->getMockConnection();
        $event1 = $this->getMockEvent($connection1);
        $event2 = $this->getMockEvent($connection2);
        $queue1 = $this->getMockQueue();
        $queue2 = $this->getMockQueue();
        Phake::when($this->queueFactory)->getEventQueue($connection1)->thenReturn($queue1);
        Phake::when($this->queueFactory)->getEventQueue($connection2)->thenReturn($queue2);

        $caller = $this->getMockTimer();
        Phake::when($caller)->getData()->thenReturn($connection1);

        $plugin = $this->getPlugin();
        $timers = $plugin->getTimers();

        $timer1 = $this->getMockTimer();
        Phake::when($this->loop)->addTimer->thenReturn($timer1);
        $plugin->handleReceived($event1, $queue1);
        Phake::verify($timer1)->setData($connection1);
        $this->assertSame($timer1, $timers->offsetGet($connection1));
        $this->assertFalse($timers->contains($connection2));

        $timer2 = $this->getMockTimer();
        Phake::when($this->loop)->addTimer->thenReturn($timer2);
        $plugin->callbackPhoneHome($caller);
        Phake::verify($queue1)->ctcpPing('BotNick', $this->anything());
        Phake::verify($timer2)->setData($connection1);
        $this->assertSame($timer2, $timers->offsetGet($connection1));
        $this->assertFalse($timers->contains($connection2));

        $plugin->callbackGrimReaper($caller);
        Phake::verify($queue1)->ircQuit();

        Phake::verifyNoInteraction($queue2);
    }
}
