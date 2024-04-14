<?php

declare(strict_types=1);

namespace Flow;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @template T
 */
interface IpStrategyInterface extends EventSubscriberInterface {}
