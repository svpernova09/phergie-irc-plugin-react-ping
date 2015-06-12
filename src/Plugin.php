<?php
/**
 * Phergie plugin for verifying that the client connection has not been dropped by sending self CTCP PINGs after periods of inactivity. (https://github.com/Renegade334/phergie-irc-plugin-react-ping)
 *
 * @link https://github.com/Renegade334/phergie-irc-plugin-react-ping for the canonical source repository
 * @copyright Copyright (c) 2015 Renegade334 (http://www.renegade334.me.uk/)
 * @license http://phergie.org/license Simplified BSD License
 * @package Renegade334\Phergie\Plugin\React\Ping
 */

namespace Renegade334\Phergie\Plugin\React\Ping;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\ConnectionInterface;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Event\EventInterface as Event;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

/**
 * Plugin class.
 *
 * @category Renegade334
 * @package Renegade334\Phergie\Plugin\React\Ping
 */
class Plugin extends AbstractPlugin
{
    const ERR_INVALID_WAIT = 1;
    const ERR_INVALID_TIMEOUT = 2;

    /**
     * How long to wait before pinging.
     *
     * @var int
     */
    protected $wait = 90;

    /**
     * How long to wait for ping to arrive.
     *
     * @var int
     */
    protected $timeout = 20;

    /**
     * Active timers.
     *
     * @var \SplObjectStorage
     */
    protected $timers;

    /**
     * Get timers store.
     *
     * @return \SplObjectStorage
     */
    public function getTimers()
    {
        if (!$this->timers) {
            $this->timers = new \SplObjectStorage;
        }
        return $this->timers;
    }

    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     * wait - How long a period of inactivity should be before verifying the connection
     * by sending a CTCP PING
     *
     * timeout - How long after sending the CTCP PING to wait for a response before assuming
     * a dead connecton
     *
     * @param array $config
     * @throws \DomainException if any config variable is invalid
     */
    public function __construct(array $config = [])
    {
        // Sanity checks for parameters
        foreach (array(
            'wait' => self::ERR_INVALID_WAIT,
            'timeout' => self::ERR_INVALID_TIMEOUT,
        ) as $var => $errcode) {
            if (isset($config[$var])) {
                if (!is_int($config[$var])) {
                    throw new \DomainException(
                        "Expected type int for $var, but found " . gettype($config['wait']),
                        $errcode
                    );
                }
                if ($config[$var] < 1) {
                    throw new \DomainException("$var must be a positive integer", $errcode);
                }
                $this->$var = $config[$var];
            }
        }
    }

    /**
     * Listen for all incoming traffic.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'connect.end' => 'handleDisconnect',
            'irc.received.each' => 'handleReceived',
        );
    }

    /**
     * Forget about a connection on disconnect.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function handleDisconnect(ConnectionInterface $connection, LoggerInterface $logger)
    {
        $timers = $this->getTimers();
        if ($timers->contains($connection)) {
            $this->getLogger()->debug('Detaching activity listener for disconnected connection');
            $timers->offsetGet($connection)->cancel();
            $timers->detach($connection);
        }
    }

    /**
     * Neutralise the existing timer, if present, and create a new timer.
     *
     * @param \Phergie\Irc\Event\EventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleReceived(Event $event, Queue $queue)
    {
        $connection = $event->getConnection();
        $timers = $this->getTimers();
        if ($timers->contains($connection)) {
            $timers->offsetGet($connection)->cancel();
        } else {
            $this->getLogger()->debug('Attaching activity listener for connection');
        }

        $timer = $this->getLoop()->addTimer($this->wait, array($this, 'callbackPhoneHome'));
        $timer->setData($connection);
        $timers->attach($connection, $timer);
    }

    /**
     * Callback called after the inactivity period is reached.
     *
     * @param \React\EventLoop\Timer\TimerInterface $caller
     */
    public function callbackPhoneHome(TimerInterface $caller)
    {
        $connection = $caller->getData();

        $this->getLogger()->debug('Inactivity period reached, sending CTCP PING');
        $this->getEventQueueFactory()->getEventQueue($connection)->ctcpPing($connection->getNickname(), time());

        $timer = $this->getLoop()->addTimer($this->timeout, array($this, 'callbackGrimReaper'));
        $timer->setData($connection);
        $this->getTimers()->attach($connection, $timer);
    }

    /**
     * Callback called after the CTCP PING timeout is reached.
     *
     * @param \React\EventLoop\Timer\TimerInterface $caller
     */
    public function callbackGrimReaper(TimerInterface $caller)
    {
        $connection = $caller->getData();

        $this->getLogger()->debug('CTCP PING timeout reached, closing connection');
        $this->getEventQueueFactory()->getEventQueue($connection)->ircQuit();
    }
}
