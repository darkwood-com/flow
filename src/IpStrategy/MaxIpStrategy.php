<?php

declare(strict_types=1);

namespace Flow\IpStrategy;

use Flow\Event;
use Flow\Event\PoolEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\IpStrategyInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @template T
 *
 * @implements IpStrategyInterface<T>
 */
class MaxIpStrategy implements IpStrategyInterface
{
    /**
     * @var IpStrategyInterface<T>
     */
    private IpStrategyInterface $ipStrategy;

    private EventDispatcherInterface $dispatcher;

    private int $processing = 0;

    /**
     * @param null|IpStrategyInterface<T> $ipStrategy
     */
    public function __construct(
        private int $max = 1,
        ?IpStrategyInterface $ipStrategy = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
        $this->dispatcher = $dispatcher ?? new EventDispatcher();
        $this->dispatcher->addSubscriber($this->ipStrategy);
    }

    public static function getSubscribedEvents()
    {
        return [
            Event::PUSH => 'push',
            Event::PULL => 'pull',
            Event::POP => 'pop',
            Event::POOL => 'pool',
        ];
    }

    /**
     * @param PushEvent<T> $event
     */
    public function push(PushEvent $event): void
    {
        $this->dispatcher->dispatch($event, Event::PUSH);
    }

    /**
     * @param PullEvent<T> $event
     */
    public function pull(PullEvent $event): void
    {
        $ips = $this->dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
        foreach ($ips as $ip) {
            if ($this->processing < $this->max) {
                $this->processing++;
                $event->addIp($ip);
            } else {
                $this->dispatcher->dispatch(new PushEvent($ip), Event::PUSH);
            }
        }
    }

    /**
     * @param PopEvent<T> $event
     */
    public function pop(PopEvent $event): void
    {
        $this->dispatcher->dispatch($event, Event::POP);
        $this->processing--;
    }

    public function pool(PoolEvent $event): void
    {
        $event->addIps($this->dispatcher->dispatch($event, Event::POOL)->getIps());
    }
}
