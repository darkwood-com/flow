<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\IpPool;

/**
 * @template T
 *
 * @implements AsyncHandlerInterface<T>
 */
final class AsyncHandler implements AsyncHandlerInterface
{
    /**
     * @var IpPool<T>
     */
    private IpPool $ipPool;

    public function __construct()
    {
        $this->ipPool = new IpPool();
    }

    public static function getSubscribedEvents()
    {
        return [
            Event::ASYNC => 'async',
            Event::POOL => 'pool',
        ];
    }

    public function async(AsyncEvent $event): void
    {
        $ip = $event->getIp();
        $async = $event->getAsync();
        $asyncJob = $async($event->getJob());

        $popIp = $this->ipPool->addIp($ip);
        $next = $asyncJob($ip->data);
        $next(static function ($data) use ($event, $popIp) {
            $event->getCallback()($data);
            $popIp();
        });
    }

    public function pool(PoolEvent $event): void
    {
        $event->addIps($this->ipPool->getIps());
    }
}
