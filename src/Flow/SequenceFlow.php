<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\Ip;
use Flow\FlowInterface;
use SplObjectStorage;
use Throwable;

class SequenceFlow implements FlowInterface
{
    /**
     * @var SplObjectStorage<Ip, mixed>
     */
    private SplObjectStorage $ips;

    /**
     * @param array<int, FlowInterface> $flows
     */
    public function __construct(private array $flows)
    {
        $this->ips = new SplObjectStorage();
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        if (0 === count($this->flows)) {
            return;
        }

        $this->ips->offsetSet($ip, $context);
        ($this->flows[0])($ip);
    }

    public function pipe(Closure $callback): void
    {
        foreach ($this->flows as $index => $flow) {
            if ($index + 1 < count($this->flows)) {
                $flow->pipe(function (Ip $ip, Throwable $exception = null) use ($index, $callback) {
                    if ($exception) {
                        $this->ips->offsetUnset($ip);
                        $callback($ip, $exception);
                    } else {
                        ($this->flows[$index + 1])($ip, $this->ips->offsetGet($ip));
                    }
                });
            } else {
                $flow->pipe(function (Ip $ip, Throwable $exception = null) use ($callback) {
                    $this->ips->offsetUnset($ip);
                    $callback($ip, $exception);
                });
            }
        }
    }
}
