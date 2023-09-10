<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function function_exists;
use function React\Async\async;
use function React\Async\delay;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class ReactDriver implements DriverInterface
{
    private LoopInterface $eventLoop;

    /**
     * @var array<string>
     */
    private array $ticksIds = [];

    public function __construct(LoopInterface $eventLoop = null)
    {
        if (!function_exists('React\\Async\\async')) {
            throw new NativeRuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop');
        }

        $this->eventLoop = $eventLoop ?? Loop::get();
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return static function (...$args) use ($callback, $onResolve): void {
            async(static function () use ($callback, $onResolve, $args) {
                try {
                    $return = $callback(...$args, ...($args = []));
                    if ($onResolve) {
                        $onResolve($return);
                    }
                } catch (Throwable $exception) {
                    if ($onResolve) {
                        $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                    }
                }
            })();
        };
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = uniqid('flow_react_tick_id');

        $timer = $this->eventLoop->addPeriodicTimer($interval, $callback);

        return function () use ($tickId, $timer) {
            unset($this->ticksIds[$tickId]);
            $this->eventLoop->cancelTimer($timer);
        };
    }

    public function start(): void
    {
        $this->eventLoop->run();
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $cancel) {
            $cancel();
        }

        $this->eventLoop->stop();
    }
}
