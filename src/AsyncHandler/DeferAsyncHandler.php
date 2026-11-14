<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\Exception\LogicException;
use Flow\IpPool;

/**
 * @template T
 *
 * @implements AsyncHandlerInterface<T>
 */
final class DeferAsyncHandler implements AsyncHandlerInterface
{
    /**
     * @var IpPool<T>
     */
    private IpPool $ipPool;

    public function __construct()
    {
        $this->ipPool = new IpPool();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Event::ASYNC => 'async',
            Event::POOL => 'pool',
        ];
    }

    public function async(AsyncEvent $event): void
    {
        $ip = $event->getIp();
        $job = $event->getJob();

        $complete = static function ($result) use ($event): void {
            if (!$event->settle()) {
                $event->fail(new LogicException('A deferred continuation completed more than once.'));

                return;
            }

            [$data] = $result;
            $event->getCallback()($data);
        };

        $event->execute(static function () use ($job, $ip, $event, $complete): void {
            $next = $job([$ip->data, $event->getDefer()]);
            $next($complete);
        }, $this->ipPool->addIp($ip));
    }

    public function pool(PoolEvent $event): void
    {
        $event->addIps($this->ipPool->getIps());
    }
}
