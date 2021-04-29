<?php

declare(strict_types=1);

namespace RFBP\Driver;

use function Amp\call;
use Amp\Loop;
use Closure;
use RFBP\DriverInterface;
use RuntimeException;

class AmpDriver implements DriverInterface
{
    /**
     * @var array<string>
     */
    private array $ticksIds;

    public function __construct()
    {
        if (!function_exists('Amp\\call')) {
            throw new RuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp');
        }

        $this->ticksIds = [];
    }

    public function coroutine(Closure $callback, ?Closure $onResolved = null): Closure
    {
        return static function (...$args) use ($callback, $onResolved): void {
            $promise = call($callback, ...$args);
            if ($onResolved) {
                $promise->onResolve($onResolved);
            }
        };
    }

    public function tick(int $interval, Closure $callback): void
    {
        $this->ticksIds[] = Loop::repeat($interval, $callback);
    }

    public function run(): void
    {
        Loop::run();
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $tickId) {
            Loop::cancel($tickId);
        }
        $this->ticksIds = [];

        Loop::stop();
    }
}
