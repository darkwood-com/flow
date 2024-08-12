<?php

declare(strict_types=1);

namespace Flow;

use Flow\Event\AsyncEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface AsyncHandlerInterface extends EventSubscriberInterface
{
    public function async(AsyncEvent $event): void;
}
