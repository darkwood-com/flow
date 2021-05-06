<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\IpStrategy\LinearIpStrategy;
use RFBP\IpStrategyInterface;
use RFBP\RailInterface;
use SplObjectStorage;
use Throwable;
use LogicException;

class ParallelRail implements RailInterface
{
    /**
     * @var Rail[]
     */
    private array $rails;

    /**
     * @var SplObjectStorage<Ip, int>
     */
    private SplObjectStorage $ips;

    /**
     * @param array<Closure> $jobs
     */
    public function __construct(
        array $jobs,
        private ?IpStrategyInterface $ipStrategy = null,
        private ?DriverInterface $driver = null
    ) {
        $this->rails = array_map(function(Closure $job) {
            return new Rail($job, $this->ipStrategy, $this->driver);
        }, $jobs);
        $this->ips = new SplObjectStorage();
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        $this->ips->offsetSet($ip, 0);
        foreach ($this->rails as $rail) {
            ($rail)($ip, $context);
        }
    }

    public function pipe(Closure $callback): void
    {
        foreach ($this->rails as $rail) {
            $rail->pipe(function($ip, Throwable $exception = null) use ($callback) {
                $count = $this->ips->offsetGet($ip) + 1;
                if($exception || $count === count($this->rails)) {
                    $this->ips->offsetUnset($ip);
                    $callback($ip, $exception);
                    return;
                }

                $this->ips->offsetSet($ip, $count);
            });
        }
    }
}
