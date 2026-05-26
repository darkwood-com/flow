<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;

use function count;

/**
 * @template T
 *
 * @implements AsyncHandlerInterface<T>
 */
final class BatchAsyncHandler implements AsyncHandlerInterface
{
    /**
     * @var array<AsyncEvent<T>>
     */
    private array $jobs = [];

    /**
     * @var AsyncHandlerInterface<T>
     */
    private AsyncHandlerInterface $asyncHandler;

    /**
     * @param null|AsyncHandlerInterface<T> $asyncHandler
     */
    public function __construct(
        private int $batchSize = 10,
        ?AsyncHandlerInterface $asyncHandler = null,
    ) {
        $this->asyncHandler = $asyncHandler ?? new AsyncHandler();
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
        $this->jobs[] = [$event];

        if (!$this->shouldFlush()) {
            return;
        }

        $jobs = $this->jobs;
        $this->jobs = [];

        foreach ($jobs as [$event]) {
            $this->asyncHandler->async($event);
        }
    }

    public function pool(PoolEvent $event): void
    {
        $this->asyncHandler->pool($event);
    }

    private function shouldFlush(): bool
    {
        return $this->batchSize <= count($this->jobs);
    }
}
