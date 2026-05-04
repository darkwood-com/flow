<?php

declare(strict_types=1);

namespace Flow;

final class Event
{
    /**
     * The PUSH event occurs at the very beginning of Flow dispatching before any async process execution.
     *
     * This event allows you to push an IP before Flow execution.
     *
     * @Event("Flow\Event\PushEvent")
     */
    public const PUSH = 'push';

    /**
     * The ASYNC event occurs when Flow dispatch async process execution.
     *
     * This event allows you to process async for an IP in the Flow execution.
     *
     * @Event("Flow\Event\AsyncEvent")
     */
    public const ASYNC = 'async';

    /**
     * The PULL event occurs when Flow need a next IP to async process.
     *
     * This event allows you to choose what IPs come next from your pushed IPs and will be used for async process execution.
     *
     * @Event("Flow\Event\PullEvent")
     */
    public const PULL = 'pull';

    /**
     * The POP event occurs when Flow finish async process of an IP.
     *
     * This event allows you to take into account that IP went asyncronously proceed throught the Flow and can now jump to the next one.
     *
     * @Event("Flow\Event\PopEvent")
     */
    public const POP = 'pop';

    /**
     * The POOL event occurs when Flow need to get the pool IPs to process.
     *
     * This event allows you to get the pool IPs to process.
     *
     * @Event("Flow\Event\PoolEvent")
     */
    public const POOL = 'pool';
}
