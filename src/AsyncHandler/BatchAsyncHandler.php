<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

/**
 * @template T1
 * @template T2
 *
 * @implements AsyncHandlerInterface<T1>
 */
final class BatchAsyncHandler implements BatchHandlerInterface, AsyncHandlerInterface
{
    use BatchHandlerTrait;

    /**
     * @var AsyncHandlerInterface<T2>
     */
    private AsyncHandlerInterface $asyncHandler;

    /**
     * @param null|AsyncHandlerInterface<T2> $asyncHandler
     */
    public function __construct(
        private int $batchSize = 10,
        ?AsyncHandlerInterface $asyncHandler = null,
    ) {
        $this->asyncHandler = $asyncHandler ?? new AsyncHandler();
    }

    public static function getSubscribedEvents()
    {
        return [
            Event::ASYNC => 'async',
        ];
    }

    public function async(AsyncEvent $event): void
    {
        $ack = new Acknowledger(get_debug_type($this), function (?Throwable $e = null, $event = null) {
            $this->asyncHandler->async($event);
        });

        $this->handle($event, $ack);
    }

    /**
     * PHPStan should normaly pass for method.unused
     * https://github.com/phpstan/phpstan/issues/6039
     * https://phpstan.org/r/8f7de023-9888-4dcb-b12c-e2fcf9547b6c.
     *
     * @param array{0: AsyncEvent<T1>, 1: Acknowledger}[] $jobs
     *
     * @phpstan-ignore method.unused
     */
    private function process(array $jobs): void
    {
        foreach ($jobs as [$event, $ack]) {
            $ack->ack($event);
        }
    }

    /**
     * PHPStan should normaly pass for method.unused
     * https://github.com/phpstan/phpstan/issues/6039
     * https://phpstan.org/r/8f7de023-9888-4dcb-b12c-e2fcf9547b6c.
     *
     * @phpstan-ignore method.unused
     */
    private function getBatchSize(): int
    {
        return $this->batchSize;
    }
}
