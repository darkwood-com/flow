<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\Ip;

use function array_map;
use function array_shift;
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
        $this->jobs[] = $event;

        if (!$this->shouldFlush()) {
            return;
        }

        $this->flush();
    }

    public function pool(PoolEvent $event): void
    {
        $this->asyncHandler->pool($event);

        if ([] === $this->jobs) {
            return;
        }

        if ([] === $event->getIps()) {
            $this->flush();
            $this->asyncHandler->pool($event);

            return;
        }

        $event->addIps(array_map(
            static fn (AsyncEvent $job): Ip => $job->getIp(),
            $this->jobs,
        ));
    }

    private function shouldFlush(): bool
    {
        return $this->batchSize <= count($this->jobs);
    }

    private function flush(): void
    {
        while ([] !== $this->jobs) {
            $event = array_shift($this->jobs);
            $this->asyncHandler->async($event);
        }
    }
}
