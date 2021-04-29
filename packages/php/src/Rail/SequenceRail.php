<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use RFBP\Ip;
use RFBP\RailInterface;
use SplObjectStorage;
use Throwable;

class SequenceRail implements RailInterface
{
    /**
     * @var SplObjectStorage<Ip, mixed>
     */
    private SplObjectStorage $ips;

    /**
     * @param array<RailInterface> $rails
     */
    public function __construct(private array $rails)
    {
        $this->ips = new SplObjectStorage();
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        if (0 === count($this->rails)) {
            return;
        }

        $this->ips->offsetSet($ip, $context);
        ($this->rails[0])($ip);
    }

    public function pipe(Closure $callback): void
    {
        foreach ($this->rails as $index => $rail) {
            if ($index + 1 < count($this->rails)) {
                $rail->pipe(function ($ip, Throwable $exception = null) use ($index, $callback) {
                    if ($exception) {
                        $this->ips->offsetUnset($ip);
                        $callback($ip, $exception);
                    } else {
                        ($this->rails[$index + 1])($ip, $this->ips->offsetGet($ip));
                    }
                });
            } else {
                $rail->pipe(function ($ip, Throwable $exception = null) use ($callback) {
                    $this->ips->offsetUnset($ip);
                    $callback($ip, $exception);
                });
            }
        }
    }
}
