<?php

declare(strict_types=1);

namespace Flow\Driver;

use Flow\Event;
use Flow\Event\PoolEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function count;

trait DriverTrait
{
    /**
     * @param array<EventDispatcherInterface> $dispatchers
     */
    public function countIps(array $dispatchers): int
    {
        $count = 0;
        foreach ($dispatchers as $dispatcher) {
            $count += count($dispatcher->dispatch(new PoolEvent(), Event::POOL)->getIps());
        }

        return $count;
    }
}
